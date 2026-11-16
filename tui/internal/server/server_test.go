package server

import (
	"bytes"
	"context"
	"io"
	"net"
	"os"
	"path/filepath"
	"strings"
	"sync"
	"testing"
	"time"

	"charm.land/log/v2"
	gossh "golang.org/x/crypto/ssh"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/api/apitest"
	"github.com/rjp2525/reno.sh/tui/internal/config"
)

// syncBuffer is a goroutine-safe log sink.
type syncBuffer struct {
	mu  sync.Mutex
	buf bytes.Buffer
}

func (b *syncBuffer) Write(p []byte) (int, error) {
	b.mu.Lock()
	defer b.mu.Unlock()
	return b.buf.Write(p)
}

func (b *syncBuffer) String() string {
	b.mu.Lock()
	defer b.mu.Unlock()
	return b.buf.String()
}

func startServer(t *testing.T, maxSessions int) (addr string, srv *Server, logs *syncBuffer, stop func()) {
	t.Helper()
	fake := apitest.New()
	t.Cleanup(fake.Close)

	cfg, err := config.FromLookup(func(string) (string, bool) { return "", false })
	if err != nil {
		t.Fatal(err)
	}
	cfg.APIBase = fake.APIBase()
	cfg.HostKeyPath = filepath.Join(t.TempDir(), "keys", "host_ed25519")
	cfg.MaxSessions = maxSessions
	cfg.ShutdownGrace = 2 * time.Second

	logs = &syncBuffer{}
	logger := log.New(logs)
	client := api.New(api.Options{BaseURL: cfg.APIBase, TTL: time.Minute})
	srv, err = New(cfg, client, logger)
	if err != nil {
		t.Fatal(err)
	}
	if _, err := os.Stat(cfg.HostKeyPath); err != nil {
		t.Fatalf("host key should be generated: %v", err)
	}

	ln, err := net.Listen("tcp", "127.0.0.1:0")
	if err != nil {
		t.Fatal(err)
	}
	ctx, cancel := context.WithCancel(context.Background())
	done := make(chan error, 1)
	go func() { done <- srv.Serve(ctx, ln) }()
	stop = func() {
		cancel()
		select {
		case err := <-done:
			if err != nil {
				t.Errorf("serve: %v", err)
			}
		case <-time.After(5 * time.Second):
			t.Error("graceful shutdown timed out")
		}
	}
	return ln.Addr().String(), srv, logs, stop
}

func dial(t *testing.T, addr string) *gossh.Client {
	t.Helper()
	c, err := gossh.Dial("tcp", addr, &gossh.ClientConfig{
		User:            "visitor",
		HostKeyCallback: gossh.InsecureIgnoreHostKey(),
		Timeout:         3 * time.Second,
	})
	if err != nil {
		t.Fatal(err)
	}
	return c
}

func TestNoPTYGetsHint(t *testing.T) {
	addr, _, logs, stop := startServer(t, 10)
	defer stop()
	c := dial(t, addr)
	defer c.Close()
	sess, err := c.NewSession()
	if err != nil {
		t.Fatal(err)
	}
	out, _ := sess.CombinedOutput("")
	if !strings.Contains(string(out), "interactive terminal app") {
		t.Fatalf("expected hint, got %q", out)
	}
	for _, line := range strings.Split(logs.String(), "\n") {
		if strings.Contains(line, "connect") && (strings.Contains(line, "visitor") || strings.Contains(line, "127.0.0.1")) {
			t.Fatalf("connection logs must not contain user names or full IPs:\n%s", logs.String())
		}
	}
	if !strings.Contains(logs.String(), "127.0.0.0/24") {
		t.Fatalf("expected masked address in logs:\n%s", logs.String())
	}
}

func TestPTYSessionRendersAndLimit(t *testing.T) {
	addr, srv, _, stop := startServer(t, 1)
	defer stop()

	c := dial(t, addr)
	defer c.Close()
	sess, err := c.NewSession()
	if err != nil {
		t.Fatal(err)
	}
	if err := sess.RequestPty("xterm-256color", 40, 100, gossh.TerminalModes{}); err != nil {
		t.Fatal(err)
	}
	stdout, _ := sess.StdoutPipe()
	stdin, _ := sess.StdinPipe()
	if err := sess.Shell(); err != nil {
		t.Fatal(err)
	}
	got := make(chan string, 1)
	go func() {
		var buf bytes.Buffer
		tmp := make([]byte, 4096)
		deadline := time.Now().Add(5 * time.Second)
		for time.Now().Before(deadline) {
			n, err := stdout.Read(tmp)
			buf.Write(tmp[:n])
			if strings.Contains(buf.String(), "_contact-me") || err != nil {
				break
			}
		}
		got <- buf.String()
		_, _ = io.Copy(io.Discard, stdout)
	}()
	if out := <-got; !strings.Contains(out, "_contact-me") {
		t.Fatalf("TUI did not render over SSH: %q", out)
	}
	if srv.ActiveSessions() != 1 {
		t.Fatalf("active sessions = %d", srv.ActiveSessions())
	}

	// A second session exceeds the cap of 1.
	c2 := dial(t, addr)
	defer c2.Close()
	s2, err := c2.NewSession()
	if err != nil {
		t.Fatal(err)
	}
	_ = s2.RequestPty("xterm", 40, 100, gossh.TerminalModes{})
	out, _ := s2.CombinedOutput("")
	if !strings.Contains(string(out), "crowded") {
		t.Fatalf("expected session-limit message, got %q", out)
	}

	_, _ = stdin.Write([]byte{3}) // ctrl+c quits the app
	_ = sess.Wait()
}

func TestMaskAddr(t *testing.T) {
	cases := map[string]string{
		"203.0.113.77:51234":         "203.0.113.0/24",
		"[2001:db8:abcd:12::1]:2222": "2001:db8:abcd::/48",
	}
	for in, want := range cases {
		a, _ := net.ResolveTCPAddr("tcp", in)
		if got := MaskAddr(a); got != want {
			t.Errorf("MaskAddr(%s) = %s, want %s", in, got, want)
		}
	}
	if MaskAddr(nil) != "unknown" {
		t.Error("nil addr")
	}
}

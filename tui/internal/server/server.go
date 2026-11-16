// Package server serves the TUI over SSH using Wish.
package server

import (
	"context"
	"errors"
	"fmt"
	"net"
	"os"
	"path/filepath"
	"strconv"
	"sync/atomic"
	"time"

	tea "charm.land/bubbletea/v2"
	"charm.land/log/v2"
	"charm.land/ssh"
	"charm.land/wish/v2"
	"charm.land/wish/v2/activeterm"
	bm "charm.land/wish/v2/bubbletea"
	"charm.land/wish/v2/ratelimiter"
	"golang.org/x/time/rate"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/config"
	"github.com/rjp2525/reno.sh/tui/internal/ui"
)

// Server wraps a configured Wish SSH server.
type Server struct {
	cfg    config.Config
	client *api.Client
	log    *log.Logger
	ssh    *ssh.Server
	active atomic.Int64
}

// New builds the SSH server, creating the host key if it does not exist.
func New(cfg config.Config, client *api.Client, logger *log.Logger) (*Server, error) {
	if logger == nil {
		logger = log.Default()
	}
	s := &Server{cfg: cfg, client: client, log: logger}

	if dir := filepath.Dir(cfg.HostKeyPath); dir != "" {
		if err := os.MkdirAll(dir, 0o700); err != nil {
			return nil, fmt.Errorf("create host key dir: %w", err)
		}
	}

	addr := net.JoinHostPort(cfg.ListenHost, strconv.Itoa(cfg.SSHPort))
	srv, err := wish.NewServer(
		wish.WithAddress(addr),
		wish.WithHostKeyPath(cfg.HostKeyPath),
		wish.WithIdleTimeout(cfg.IdleTimeout),
		wish.WithMaxTimeout(cfg.MaxSession),
		wish.WithVersion("reno.sh"),
		wish.WithMiddleware(
			// Executed bottom-up: logging → rate limit → session cap → PTY check → app.
			bm.Middleware(s.teaHandler),
			activeterm.Middleware(),
			s.ptyHint(),
			s.sessionLimit(),
			ratelimiter.Middleware(ratelimiter.NewRateLimiter(rate.Every(2*time.Second), 10, 4096)),
			s.logging(),
		),
	)
	if err != nil {
		return nil, err
	}
	s.ssh = srv
	return s, nil
}

// Addr is the listen address.
func (s *Server) Addr() string { return s.ssh.Addr }

// ListenAndServe runs until ctx is cancelled, then shuts down gracefully,
// giving open sessions up to cfg.ShutdownGrace to finish.
func (s *Server) ListenAndServe(ctx context.Context) error {
	ln, err := net.Listen("tcp", s.ssh.Addr)
	if err != nil {
		return err
	}
	return s.Serve(ctx, ln)
}

// Serve is ListenAndServe on an existing listener (used by tests).
func (s *Server) Serve(ctx context.Context, ln net.Listener) error {
	errCh := make(chan error, 1)
	go func() {
		s.log.Info("ssh server listening", "addr", ln.Addr().String(), "api", s.cfg.APIBase)
		errCh <- s.ssh.Serve(ln)
	}()
	select {
	case err := <-errCh:
		if errors.Is(err, ssh.ErrServerClosed) {
			return nil
		}
		return err
	case <-ctx.Done():
	}
	s.log.Info("shutting down", "active_sessions", s.active.Load(), "grace", s.cfg.ShutdownGrace)
	sctx, cancel := context.WithTimeout(context.Background(), s.cfg.ShutdownGrace)
	defer cancel()
	if err := s.ssh.Shutdown(sctx); err != nil && !errors.Is(err, ssh.ErrServerClosed) {
		s.log.Warn("forcing close", "err", err)
		_ = s.ssh.Close()
	}
	return nil
}

// ActiveSessions reports the number of sessions currently running.
func (s *Server) ActiveSessions() int64 { return s.active.Load() }

func (s *Server) teaHandler(sess ssh.Session) (tea.Model, []tea.ProgramOption) {
	m := ui.New(ui.Options{
		Client:      s.client,
		SiteURL:     s.cfg.SiteURL,
		SSHHost:     s.cfg.SSHHost,
		SSHPort:     s.cfg.SSHPort,
		IdleTimeout: s.cfg.IdleTimeout,
		Context:     sess.Context(),
	})
	return m, []tea.ProgramOption{tea.WithFPS(30)}
}

// ptyHint explains how to connect when the client did not request a PTY
// (e.g. `ssh -T` or running a remote command). activeterm then rejects it.
func (s *Server) ptyHint() wish.Middleware {
	return func(next ssh.Handler) ssh.Handler {
		return func(sess ssh.Session) {
			if _, _, ok := sess.Pty(); !ok {
				wish.Printf(sess, "reno.sh is an interactive terminal app. connect from a terminal with: ssh %s -p %d\n",
					s.cfg.SSHHost, s.cfg.SSHPort)
			}
			next(sess)
		}
	}
}

// sessionLimit caps concurrent sessions.
func (s *Server) sessionLimit() wish.Middleware {
	return func(next ssh.Handler) ssh.Handler {
		return func(sess ssh.Session) {
			n := s.active.Add(1)
			defer s.active.Add(-1)
			if s.cfg.MaxSessions > 0 && n > int64(s.cfg.MaxSessions) {
				s.log.Warn("session limit reached", "limit", s.cfg.MaxSessions)
				wish.Println(sess, "reno.sh is a little crowded right now. please try again in a minute.")
				_ = sess.Exit(1)
				return
			}
			next(sess)
		}
	}
}

// logging records connects/disconnects without anything sensitive: the
// client IP is truncated (/24 for IPv4, /48 for IPv6) and usernames, keys,
// environment and keystrokes are never logged.
func (s *Server) logging() wish.Middleware {
	return func(next ssh.Handler) ssh.Handler {
		return func(sess ssh.Session) {
			start := time.Now()
			pty, _, hasPTY := sess.Pty()
			remote := MaskAddr(sess.RemoteAddr())
			s.log.Info("connect", "remote", remote, "pty", hasPTY, "term", pty.Term,
				"cols", pty.Window.Width, "rows", pty.Window.Height, "active", s.active.Load()+1)
			next(sess)
			s.log.Info("disconnect", "remote", remote, "duration", time.Since(start).Round(time.Second))
		}
	}
}

// MaskAddr anonymises a remote address for logging.
func MaskAddr(addr net.Addr) string {
	if addr == nil {
		return "unknown"
	}
	host, _, err := net.SplitHostPort(addr.String())
	if err != nil {
		host = addr.String()
	}
	ip := net.ParseIP(host)
	if ip == nil {
		return "unknown"
	}
	if v4 := ip.To4(); v4 != nil {
		return (&net.IPNet{IP: v4.Mask(net.CIDRMask(24, 32)), Mask: net.CIDRMask(24, 32)}).String()
	}
	return (&net.IPNet{IP: ip.Mask(net.CIDRMask(48, 128)), Mask: net.CIDRMask(48, 128)}).String()
}

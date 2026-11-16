package ui

import (
	"context"
	"reflect"
	"strings"
	"testing"
	"time"

	"charm.land/bubbles/v2/spinner"
	tea "charm.land/bubbletea/v2"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/api/apitest"
)

// harness drives a Model synchronously: commands are executed (with a short
// timeout so timers never block) and their messages fed back into Update.
type harness struct {
	t   *testing.T
	m   *Model
	srv *apitest.Server
}

func newHarness(t *testing.T, w, h int) *harness {
	t.Helper()
	srv := apitest.New()
	t.Cleanup(srv.Close)
	client := api.New(api.Options{BaseURL: srv.APIBase(), TTL: time.Minute, Timeout: 2 * time.Second})
	m := New(Options{Client: client, SiteURL: "https://reno.sh", Context: context.Background(), IdleTimeout: time.Minute})
	hs := &harness{t: t, m: m, srv: srv}
	hs.send(tea.WindowSizeMsg{Width: w, Height: h})
	hs.run(m.Init())
	return hs
}

func (h *harness) send(msg tea.Msg) {
	h.t.Helper()
	_, cmd := h.m.Update(msg)
	h.run(cmd)
}

func (h *harness) run(cmd tea.Cmd) {
	h.t.Helper()
	round := []tea.Cmd{cmd}
	for steps := 0; len(round) > 0 && steps < 100; steps++ {
		var next []tea.Cmd
		for _, msg := range runRound(round, 150*time.Millisecond) {
			switch msg := msg.(type) {
			case nil:
				continue
			case tea.BatchMsg:
				next = append(next, msg...)
				continue
			case spinner.TickMsg, idleTickMsg, tea.QuitMsg:
				continue // timers: don't loop forever
			}
			pkg := reflect.TypeOf(msg).PkgPath()
			if pkg == "charm.land/bubbles/v2/cursor" {
				continue // cursor blinking is an endless timer chain
			}
			if pkg == "charm.land/bubbletea/v2" && !isPublicTeaMsg(msg) {
				continue // internal requests (background colour etc.)
			}
			_, c := h.m.Update(msg)
			next = append(next, c)
		}
		round = next
	}
}

// runRound executes commands concurrently and returns the messages produced
// before the deadline; slow commands (timers) are abandoned.
func runRound(cmds []tea.Cmd, d time.Duration) []tea.Msg {
	ch := make(chan tea.Msg, len(cmds))
	n := 0
	for _, c := range cmds {
		if c == nil {
			continue
		}
		n++
		go func() { ch <- c() }()
	}
	var out []tea.Msg
	deadline := time.After(d)
	for i := 0; i < n; i++ {
		select {
		case msg := <-ch:
			out = append(out, msg)
		case <-deadline:
			return out
		}
	}
	return out
}

func isPublicTeaMsg(msg tea.Msg) bool {
	switch msg.(type) {
	case tea.WindowSizeMsg, tea.KeyPressMsg, tea.MouseWheelMsg, tea.BackgroundColorMsg, tea.ColorProfileMsg:
		return true
	}
	return false
}

func (h *harness) key(keys ...string) {
	h.t.Helper()
	for _, k := range keys {
		h.send(keyMsg(k))
	}
}

func keyMsg(k string) tea.KeyPressMsg {
	switch k {
	case "enter":
		return tea.KeyPressMsg{Code: tea.KeyEnter}
	case "tab":
		return tea.KeyPressMsg{Code: tea.KeyTab}
	case "shift+tab":
		return tea.KeyPressMsg{Code: tea.KeyTab, Mod: tea.ModShift}
	case "esc":
		return tea.KeyPressMsg{Code: tea.KeyEscape}
	case "down":
		return tea.KeyPressMsg{Code: tea.KeyDown}
	case "up":
		return tea.KeyPressMsg{Code: tea.KeyUp}
	case "ctrl+s":
		return tea.KeyPressMsg{Code: 's', Mod: tea.ModCtrl}
	case "ctrl+c":
		return tea.KeyPressMsg{Code: 'c', Mod: tea.ModCtrl}
	case "space":
		return tea.KeyPressMsg{Code: tea.KeySpace, Text: " "}
	}
	r := []rune(k)
	return tea.KeyPressMsg{Code: r[0], Text: k}
}

func (h *harness) typeText(s string) {
	// Typing only yields cursor-blink commands, so skip running them.
	for _, r := range s {
		h.m.Update(tea.KeyPressMsg{Code: r, Text: string(r)})
	}
}

func (h *harness) screen() string { return xansi.Strip(h.m.render()) }

func (h *harness) contains(want ...string) {
	h.t.Helper()
	out := h.screen()
	for _, w := range want {
		if !strings.Contains(out, w) {
			h.t.Fatalf("screen missing %q:\n%s", w, out)
		}
	}
}

func (h *harness) lacks(bad ...string) {
	h.t.Helper()
	out := h.screen()
	for _, b := range bad {
		if strings.Contains(out, b) {
			h.t.Fatalf("screen unexpectedly contains %q:\n%s", b, out)
		}
	}
}

func TestHomeScreen(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.contains("Howdy there, I'm", "█", "Full Stack Software Engineer", "_about", "_projects",
		"_photography", "_blog", "_contact-me", "reno.sh • ssh -p 2222", "githubLink")
	// Every rendered line must fit the terminal width.
	for _, line := range strings.Split(h.m.render(), "\n") {
		if w := xansi.StringWidth(line); w > 100 {
			t.Fatalf("line wider than terminal (%d): %q", w, xansi.Strip(line))
		}
	}
	if got := strings.Count(h.m.render(), "\n") + 1; got != 40 {
		t.Fatalf("view should fill the height exactly, got %d lines", got)
	}
}

func TestNarrowAndTinyTerminals(t *testing.T) {
	h := newHarness(t, 50, 20)
	h.contains("reno.sh", "‹ _hello ›", "Reno Philibert")
	h.send(tea.WindowSizeMsg{Width: 30, Height: 8})
	h.contains("terminal too small")
}

func TestSectionNavigation(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.key("tab")
	if h.m.active != secAbout {
		t.Fatalf("tab should go to about, got %v", h.m.active)
	}
	h.key("shift+tab")
	if h.m.active != secHome {
		t.Fatalf("shift+tab should go home, got %v", h.m.active)
	}
	h.key("5")
	if h.m.active != secBlog {
		t.Fatalf("5 should open blog, got %v", h.m.active)
	}
	h.key("q")
	if h.m.active != secHome {
		t.Fatalf("q at a section root goes home, got %v", h.m.active)
	}
	// Menu: down twice → _photography? first item is _about.
	h.key("down", "enter")
	if h.m.active != secProjects {
		t.Fatalf("menu enter should open projects, got %v", h.m.active)
	}
	h.key("?")
	h.contains("keyboard shortcuts", "next section")
	h.key("esc")
	h.lacks("keyboard shortcuts")
	// Clicking a tab switches sections.
	tabs := h.m.tabLayout()
	h.send(tea.MouseClickMsg{X: tabs[4].x0 + 1, Y: 0, Button: tea.MouseLeft})
	if h.m.active != tabs[4].sec {
		t.Fatalf("click should open %v, got %v", tabs[4].sec, h.m.active)
	}
	_, cmd := h.m.Update(keyMsg("ctrl+c"))
	if msg := cmd(); !reflect.DeepEqual(msg, tea.QuitMsg{}) {
		t.Fatalf("ctrl+c should quit, got %#v", msg)
	}
}

func TestAboutTabs(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.key("2")
	h.contains("[professional]", "I build", "things")
	h.key("l")
	h.contains("[personal]", "Coffee")
	h.key("l", "l")
	h.contains("[experience]", "Senior Engineer", "Acme", "present", "Led the platform team")
	h.key("l")
	h.contains("[skills]", "// backend", "Laravel", "expert", "█", "░")
	h.key("l")
	h.contains("[education]", "State University", "B.S. Computer Science", "Dean's list", "Robotics club")
}

func TestProjects(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.key("3")
	h.contains("reno.sh", "CLI Tool", "[all]", "★")
	h.key("l") // first type alphabetically: open-source
	h.contains("[open-source]", "CLI Tool")
	h.lacks("This very site.")
	h.key("h")
	h.key("enter")
	h.contains("// projects/reno-sh", "Laravel", "github", "https://github.com/rjp2525/reno.sh", "Overview", "SSH app")
	h.key("esc")
	h.contains("CLI Tool")
}

func TestBlogListSearchAndDetail(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.key("5")
	h.contains("Hello SSH", "Laravel Tips", "4 min read", "#go", "[all]")

	// Category filter refetches.
	h.key("l", "l")
	h.contains("[laravel]", "Laravel Tips")
	h.lacks("Hello SSH")
	h.key("h", "h")

	// Fuzzy search.
	h.key("/")
	if !h.m.screens[secBlog].Capturing() {
		t.Fatal("filtering should capture keys")
	}
	h.key("l", "r", "v", "l") // filtering runs as a command
	h.key("enter")
	h.contains("Laravel Tips")
	h.lacks("Hello SSH")
	h.key("esc") // clears filter
	h.contains("Hello SSH")

	h.key("enter")
	h.contains("// blog/hello-ssh", "Why SSH", "Wrapping up", "t contents", "%")
	b := h.m.screens[secBlog].(*blog)
	if !b.read.hasTOC() || len(b.read.toc) != 2 {
		t.Fatalf("expected 2 TOC entries, got %+v", b.read.toc)
	}
	h.key("t")
	h.contains("contents", "Why SSH")
	h.key("down", "enter")
	if b.read.tocOpen {
		t.Fatal("toc should close after jump")
	}
	if b.read.vp.YOffset() == 0 && b.read.vp.TotalLineCount() > b.read.vp.Height() {
		t.Fatal("jump should scroll the viewport")
	}
	h.key("q")
	if b.inDetail() {
		t.Fatal("q should return to the list")
	}
	if h.m.active != secBlog {
		t.Fatal("q from detail must stay in blog")
	}
}

func TestPhotoDetail(t *testing.T) {
	h := newHarness(t, 100, 50)
	h.send(tea.ColorProfileMsg{})
	h.key("4")
	h.contains("Mountain Dawn", "Sony A7 IV", "prints", "City Night")
	h.key("enter")
	out := h.m.render()
	if !strings.Contains(out, "▀") {
		t.Fatalf("expected half-block image:\n%s", xansi.Strip(out))
	}
	h.contains("Mountain Dawn", "★ favourite")
	h.key("G")
	h.contains("// exif", "FE 24-70mm", "Fine Art Print", "12×18 in", "$65.00", "$249.00",
		"order a print on the web", "https://reno.sh/photography/mountain-dawn")
}

func TestContactForm(t *testing.T) {
	h := newHarness(t, 100, 45)
	h.key("6")
	h.key("enter") // start editing name
	if !h.m.screens[secContact].Capturing() {
		t.Fatal("editing should capture keys")
	}
	h.key("ctrl+s")
	h.contains("name is required", "email is required", "message is required")

	h.typeText("Ada q") // q must be typed, not quit/back
	h.key("tab")
	h.typeText("ada@example")
	h.key("tab", "tab")
	h.typeText("Hello from the terminal!")
	h.key("ctrl+s")
	h.contains("doesn't look like an email")
	// Focus jumps to the first invalid field.
	h.typeText(".com")
	h.key("ctrl+s")
	h.contains("message sent", "ada@example.com")
	if len(h.srv.Contacts) != 1 || h.srv.Contacts[0]["name"] != "Ada q" {
		t.Fatalf("server got %+v", h.srv.Contacts)
	}
	h.key("n")
	h.lacks("message sent")
}

func TestContactServerErrors(t *testing.T) {
	h := newHarness(t, 100, 45)
	h.key("6", "enter")
	h.typeText("spam")
	h.key("tab")
	h.typeText("a@b.co")
	h.key("tab", "tab")
	h.typeText("this is a message")
	h.key("ctrl+s")
	h.contains("slow down")

	if errs := validateContact(api.ContactRequest{Name: "A", Email: "a@b", Message: "short"}); errs[fieldEmail] == "" || errs[fieldMessage] == "" {
		t.Fatalf("validation: %+v", errs)
	}
}

func TestAPIDown(t *testing.T) {
	srv := apitest.New()
	defer srv.Close()
	srv.SetDown(true)
	client := api.New(api.Options{BaseURL: srv.APIBase(), TTL: time.Minute, Timeout: time.Second})
	m := New(Options{Client: client})
	h := &harness{t: t, m: m, srv: srv}
	h.send(tea.WindowSizeMsg{Width: 100, Height: 40})
	h.run(m.Init())
	h.contains("Howdy there", "API is having a moment (503)")
	h.key("2")
	h.contains("press r to retry")
	srv.SetDown(false)
	h.key("r")
	h.contains("[professional]")
}

func TestIdleTimeout(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.m.lastInput = time.Now().Add(-2 * time.Minute)
	_, cmd := h.m.Update(idleTickMsg(time.Now()))
	if cmd == nil || !reflect.DeepEqual(cmd(), tea.QuitMsg{}) {
		t.Fatal("idle session should quit")
	}
	if !h.m.IdleTimedOut() {
		t.Fatal("IdleTimedOut should be set")
	}
}

func TestLightTheme(t *testing.T) {
	h := newHarness(t, 100, 40)
	h.send(tea.BackgroundColorMsg{Color: whiteColor{}})
	if h.m.env.Theme.IsDark {
		t.Fatal("light background should switch to light theme")
	}
	h.contains("Howdy there")
}

type whiteColor struct{}

func (whiteColor) RGBA() (r, g, b, a uint32) { return 0xffff, 0xffff, 0xffff, 0xffff }

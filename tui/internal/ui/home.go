package ui

import (
	"fmt"
	"strings"

	"charm.land/bubbles/v2/key"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"

	"github.com/rjp2525/reno.sh/tui/internal/ui/components"
)

type menuItem struct {
	sec  section
	desc string
}

var homeMenu = []menuItem{
	{secAbout, "who I am, experience & skills"},
	{secProjects, "things I've built"},
	{secPhotos, "photos, EXIF & prints"},
	{secBlog, "notes on code & craft"},
	{secContact, "say hello"},
}

// home is the splash screen.
type home struct {
	env     *Env
	w, h    int
	cursor  int
	siteErr error
	loaded  bool
}

func newHome(env *Env) *home { return &home{env: env} }

func (s *home) Activate() tea.Cmd { return nil }
func (s *home) SetSize(w, h int)  { s.w, s.h = w, h }
func (s *home) Back() bool        { return false }
func (s *home) Capturing() bool   { return false }
func (s *home) Crumb() string     { return "" }

func (s *home) Keys() []key.Binding {
	return []key.Binding{
		key.NewBinding(key.WithKeys("up", "k"), key.WithHelp("↑/k", "up")),
		key.NewBinding(key.WithKeys("down", "j"), key.WithHelp("↓/j", "down")),
		key.NewBinding(key.WithKeys("enter"), key.WithHelp("enter", "open section")),
		key.NewBinding(key.WithKeys("q"), key.WithHelp("q", "quit")),
	}
}

func (s *home) items() []menuItem {
	out := make([]menuItem, 0, len(homeMenu))
	for _, it := range homeMenu {
		if s.env.Site.Name == "" || s.env.Site.HasSection(sectionAliases(it.sec)...) {
			out = append(out, it)
		}
	}
	return out
}

func sectionAliases(sec section) []string {
	switch sec {
	case secPhotos:
		return []string{"gallery", "photography", "photos", "prints"}
	default:
		return []string{strings.TrimPrefix(strings.TrimSuffix(sec.String(), "-me"), "_")}
	}
}

func (s *home) Update(msg tea.Msg) tea.Cmd {
	switch msg := msg.(type) {
	case siteMsg:
		s.loaded, s.siteErr = true, msg.err
		s.cursor = min(s.cursor, max(len(s.items())-1, 0))
	case tea.MouseWheelMsg:
		if msg.Button == tea.MouseWheelUp {
			s.move(-1)
		} else if msg.Button == tea.MouseWheelDown {
			s.move(1)
		}
	case tea.KeyPressMsg:
		switch msg.String() {
		case "up", "k":
			s.move(-1)
		case "down", "j":
			s.move(1)
		case "home", "g":
			s.cursor = 0
		case "end", "G":
			s.cursor = len(s.items()) - 1
		case "enter", "space", "l", "right":
			items := s.items()
			if s.cursor < len(items) {
				return goSection(items[s.cursor].sec)
			}
		}
	}
	return nil
}

func (s *home) move(d int) {
	n := len(s.items())
	if n == 0 {
		return
	}
	s.cursor = (s.cursor + d + n) % n
}

func (s *home) View() string {
	t := s.env.Theme
	site := s.env.Site
	name := orDefault(site.Name, "Reno Philibert")
	title := orDefault(site.Title, "Full Stack Software Engineer")
	tagline := orDefault(site.Tagline, "building awesome things for the web since 2011")
	github := "https://github.com/rjp2525"
	for _, so := range site.Socials {
		if strings.EqualFold(so.Label, "github") {
			github = so.URL
		}
	}

	inner := min(s.w-4, 100)
	var b strings.Builder
	b.WriteString(t.Body.Render("Howdy there, I'm") + "\n\n")

	banner := components.Banner(inner, t.Primary, t.Blue, name, firstWord(name), "reno.sh")
	if s.h < 22 { // not enough room for a 5-row banner and the menu
		banner = t.Title.Render(name)
	}
	b.WriteString(banner + "\n\n")
	b.WriteString(t.Keyword.Render("> ") + t.Accent.Render(title) + t.Faint.Render("▌") + "\n\n")
	b.WriteString(t.Comment.Render("// "+tagline) + "\n")
	if site.Location != "" {
		b.WriteString(t.Comment.Render("// based in "+site.Location) + "\n")
	}
	b.WriteString(t.Comment.Render("// check out my profile on Github:") + "\n")
	b.WriteString(t.Keyword.Render("const ") + t.Ident.Render("githubLink") + t.Body.Render(" = ") +
		t.String.Hyperlink(github).Render(fmt.Sprintf("%q", github)) + "\n\n")

	for i, it := range s.items() {
		label := fmt.Sprintf("%-14s", it.sec.String())
		if i == s.cursor {
			b.WriteString(t.Accent.Render("▸ ") + t.Selected.Render(label) + " " + t.Comment.Render("// "+it.desc) + "\n")
		} else {
			b.WriteString("  " + t.Body.Render(label) + " " + t.Faint.Render("// "+it.desc) + "\n")
		}
	}
	if s.siteErr != nil {
		b.WriteString("\n" + t.Warn.Render("! "+friendlyError(s.siteErr)) + "\n")
	}
	b.WriteString("\n" + t.Faint.Render("↑/↓ choose · enter open · tab cycle sections · ? help · q quit"))

	content := lipgloss.NewStyle().Width(inner).Render(b.String())
	return lipgloss.Place(s.w, s.h, lipgloss.Center, lipgloss.Center, content)
}

func orDefault(s, def string) string {
	if strings.TrimSpace(s) == "" {
		return def
	}
	return s
}

func firstWord(s string) string {
	if f := strings.Fields(s); len(f) > 0 {
		return f[0]
	}
	return s
}

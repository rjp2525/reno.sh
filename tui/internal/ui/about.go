package ui

import (
	"fmt"
	"sort"
	"strings"

	"charm.land/bubbles/v2/key"
	"charm.land/bubbles/v2/viewport"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/ui/components"
)

var aboutTabs = []string{"professional", "personal", "hobbies", "experience", "skills", "education"}

type aboutMsg struct {
	about api.About
	err   error
}

type about struct {
	env    *Env
	w, h   int
	load   loader
	data   *api.About
	tab    int
	vp     viewport.Model
	offset [6]int
}

func newAbout(env *Env) *about {
	vp := viewport.New()
	vp.MouseWheelEnabled = true
	return &about{env: env, load: newLoader(env), vp: vp}
}

func (s *about) Activate() tea.Cmd {
	if s.data != nil || s.load.loading {
		return nil
	}
	return s.fetch()
}

func (s *about) fetch() tea.Cmd {
	start := s.load.start()
	return tea.Batch(start, func() tea.Msg {
		a, err := s.env.Client.About(s.env.Ctx)
		return aboutMsg{a, err}
	})
}

func (s *about) SetSize(w, h int) {
	s.w, s.h = w, h
	s.vp.SetWidth(w)
	s.vp.SetHeight(max(h-2, 1))
	s.refresh()
}

func (s *about) Back() bool      { return false }
func (s *about) Capturing() bool { return false }
func (s *about) Crumb() string   { return aboutTabs[s.tab] }

func (s *about) Keys() []key.Binding {
	return []key.Binding{
		key.NewBinding(key.WithKeys("left", "h"), key.WithHelp("←/h", "previous tab")),
		key.NewBinding(key.WithKeys("right", "l"), key.WithHelp("→/l", "next tab")),
		key.NewBinding(key.WithKeys("j", "k"), key.WithHelp("j/k ↑/↓", "scroll")),
		key.NewBinding(key.WithKeys("pgdown"), key.WithHelp("pgup/pgdn", "page")),
		keyRefresh,
	}
}

func (s *about) Update(msg tea.Msg) tea.Cmd {
	if cmd := s.load.update(msg); cmd != nil {
		return cmd
	}
	switch msg := msg.(type) {
	case aboutMsg:
		s.load.done(msg.err)
		if msg.err == nil {
			s.data = &msg.about
			s.refresh()
		}
	case themeChangedMsg:
		s.refresh()
	case tea.KeyPressMsg:
		switch msg.String() {
		case "left", "h", "[":
			s.setTab((s.tab - 1 + len(aboutTabs)) % len(aboutTabs))
			return nil
		case "right", "l", "]":
			s.setTab((s.tab + 1) % len(aboutTabs))
			return nil
		case "r":
			if !s.load.loading {
				return s.fetch()
			}
		case "g", "home":
			s.vp.GotoTop()
			return nil
		case "G", "end":
			s.vp.GotoBottom()
			return nil
		}
		var cmd tea.Cmd
		s.vp, cmd = s.vp.Update(msg)
		return cmd
	case tea.MouseWheelMsg:
		var cmd tea.Cmd
		s.vp, cmd = s.vp.Update(msg)
		return cmd
	}
	return nil
}

func (s *about) setTab(i int) {
	s.offset[s.tab] = s.vp.YOffset()
	s.tab = i
	s.refresh()
	s.vp.SetYOffset(s.offset[i])
}

func (s *about) refresh() {
	if s.data == nil || s.w == 0 {
		return
	}
	width := min(s.w-4, 100)
	var content string
	switch aboutTabs[s.tab] {
	case "professional", "personal", "hobbies":
		content = s.pages(aboutTabs[s.tab], width)
	case "experience":
		content = s.experience(width)
	case "skills":
		content = s.skills(width)
	case "education":
		content = s.education(width)
	}
	s.vp.SetContent(lipgloss.NewStyle().Padding(0, 2).Render(content))
}

func (s *about) pages(sectionName string, width int) string {
	t := s.env.Theme
	var md []string
	for _, p := range s.data.Pages {
		if !strings.EqualFold(p.Section, sectionName) {
			continue
		}
		body := p.Markdown
		if strings.TrimSpace(body) == "" {
			body = p.Text
		}
		if !strings.HasPrefix(strings.TrimSpace(body), "#") && p.Title != "" {
			body = "## " + p.Title + "\n\n" + body
		}
		md = append(md, body)
	}
	if len(md) == 0 {
		return t.Comment.Render("// nothing here yet")
	}
	return components.RenderMarkdown(strings.Join(md, "\n\n---\n\n"), width, t.IsDark)
}

func (s *about) experience(width int) string {
	t := s.env.Theme
	if len(s.data.Experience) == 0 {
		return t.Comment.Render("// no experience listed")
	}
	rail := lipgloss.NewStyle().Foreground(t.Border)
	var b strings.Builder
	for i, e := range s.data.Experience {
		dates := components.Range(e.Start, e.End, components.MonthYear)
		head := t.Title.Render(e.Title)
		if e.Company != "" {
			head += t.Faint.Render(" @ ") + t.Ident.Render(e.Company)
		}
		pad := width - lipgloss.Width(head) - lipgloss.Width(dates) - 3
		dot := t.Accent.Render("●")
		if e.End == "" {
			dot = t.Success.Render("●")
		}
		if pad >= 1 {
			b.WriteString(dot + "  " + head + strings.Repeat(" ", pad) + t.Accent.Render(dates) + "\n")
		} else {
			b.WriteString(dot + "  " + head + "\n" + rail.Render("│") + "  " + t.Accent.Render(dates) + "\n")
		}
		if e.Location != "" {
			b.WriteString(rail.Render("│") + "  " + t.Faint.Render(e.Location) + "\n")
		}
		for _, r := range e.Responsibilities {
			wrapped := xansi.Wordwrap(r, width-7, "")
			for j, line := range strings.Split(wrapped, "\n") {
				bullet := "  "
				if j == 0 {
					bullet = t.Ident.Render("› ")
				}
				b.WriteString(rail.Render("│") + "  " + bullet + t.Body.Render(line) + "\n")
			}
		}
		if i < len(s.data.Experience)-1 {
			b.WriteString(rail.Render("│") + "\n")
		}
	}
	return b.String()
}

func (s *about) skills(width int) string {
	t := s.env.Theme
	if len(s.data.Skills) == 0 {
		return t.Comment.Render("// no skills listed")
	}
	groups := map[string][]api.Skill{}
	var order []string
	nameW := 0
	for _, sk := range s.data.Skills {
		cat := orDefault(sk.Category, "Other")
		if _, ok := groups[cat]; !ok {
			order = append(order, cat)
		}
		groups[cat] = append(groups[cat], sk)
		nameW = max(nameW, lipgloss.Width(sk.Name))
	}
	nameW = min(nameW, max(width/3, 10))
	barW := max(min(width-nameW-22, 24), 4)
	var b strings.Builder
	for gi, cat := range order {
		b.WriteString(t.Comment.Render("// "+strings.ToLower(cat)) + "\n")
		list := groups[cat]
		sort.SliceStable(list, func(i, j int) bool { return list[i].Level() > list[j].Level() })
		for _, sk := range list {
			name := xansi.Truncate(sk.Name, nameW, "…")
			name += strings.Repeat(" ", nameW-lipgloss.Width(name))
			bar := components.Bar(sk.Level(), 4, barW, t.Primary, t.Border)
			since := ""
			if sk.Started != "" {
				since = t.Faint.Render(" since " + components.Year(sk.Started))
			}
			b.WriteString("  " + t.Body.Render(name) + "  " + bar + " " +
				t.Accent.Render(fmt.Sprintf("%-12s", strings.ToLower(sk.Proficiency))) + since + "\n")
		}
		if gi < len(order)-1 {
			b.WriteString("\n")
		}
	}
	return b.String()
}

func (s *about) education(width int) string {
	t := s.env.Theme
	if len(s.data.Education) == 0 {
		return t.Comment.Render("// no education listed")
	}
	var b strings.Builder
	list := func(label string, items []string) {
		if len(items) == 0 {
			return
		}
		b.WriteString("   " + t.Keyword.Render(label) + "\n")
		for _, it := range items {
			b.WriteString("     " + t.Ident.Render("› ") + t.Body.Render(xansi.Wordwrap(it, width-8, "")) + "\n")
		}
	}
	for i, e := range s.data.Education {
		b.WriteString(t.Accent.Render("◆ ") + t.Title.Render(e.SchoolName) + "\n")
		deg := e.Degree
		if e.Minor != "" {
			deg += t.Faint.Render(", minor in ") + e.Minor
		}
		if deg != "" {
			b.WriteString("   " + t.Ident.Render(deg) + "\n")
		}
		meta := []string{}
		if e.Location != "" {
			meta = append(meta, e.Location)
		}
		if e.Started != "" || e.Graduated != "" {
			meta = append(meta, components.Range(e.Started, e.Graduated, components.Year))
		}
		if len(meta) > 0 {
			b.WriteString("   " + t.Faint.Render(strings.Join(meta, " · ")) + "\n")
		}
		if e.Description != "" {
			b.WriteString("\n   " + strings.ReplaceAll(t.Body.Render(xansi.Wordwrap(e.Description, width-4, "")), "\n", "\n   ") + "\n")
		}
		list("achievements", e.Achievements)
		list("projects", e.Projects)
		list("extracurriculars", e.Extracurriculars)
		if i < len(s.data.Education)-1 {
			b.WriteString("\n")
		}
	}
	return b.String()
}

func (s *about) View() string {
	if v := s.load.view("about", s.w, s.h); v != "" {
		return v
	}
	if s.data == nil {
		return ""
	}
	t := s.env.Theme
	tabs := " " + renderTabs(s.env, aboutTabs, s.tab, s.w-2)
	scroll := ""
	if s.vp.TotalLineCount() > s.vp.Height() {
		scroll = t.Faint.Render(fmt.Sprintf(" %3.0f%%", s.vp.ScrollPercent()*100))
	}
	head := tabs + strings.Repeat(" ", max(s.w-lipgloss.Width(tabs)-lipgloss.Width(scroll), 0)) + scroll
	return head + "\n\n" + s.vp.View()
}

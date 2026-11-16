package ui

import (
	"fmt"
	"sort"
	"strings"

	"charm.land/bubbles/v2/key"
	"charm.land/bubbles/v2/list"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/ui/components"
	"github.com/rjp2525/reno.sh/tui/internal/ui/theme"
)

type projectsMsg struct {
	items []api.ProjectSummary
	err   error
}

type projectMsg struct {
	slug string
	p    api.Project
	err  error
}

type projects struct {
	env  *Env
	w, h int

	load   loader
	all    []api.ProjectSummary
	loaded bool
	types  []string
	typ    int
	list   list.Model

	detail     *api.Project
	detailSlug string
	dload      loader
	read       reader
}

func newProjects(env *Env) *projects {
	return &projects{
		env:   env,
		load:  newLoader(env),
		dload: newLoader(env),
		list:  newList(env, "project", "projects"),
		read:  newReader(env),
		types: []string{"all"},
	}
}

func (s *projects) Activate() tea.Cmd {
	if s.loaded || s.load.loading {
		return nil
	}
	return s.fetch()
}

func (s *projects) fetch() tea.Cmd {
	return tea.Batch(s.load.start(), func() tea.Msg {
		items, err := s.env.Client.Projects(s.env.Ctx, "")
		return projectsMsg{items, err}
	})
}

func (s *projects) open(slug string) tea.Cmd {
	s.detailSlug, s.detail = slug, nil
	return tea.Batch(s.dload.start(), func() tea.Msg {
		p, err := s.env.Client.Project(s.env.Ctx, slug)
		return projectMsg{slug, p, err}
	})
}

func (s *projects) SetSize(w, h int) {
	s.w, s.h = w, h
	s.list.SetSize(w, max(h-2, 1))
	s.read.setSize(w, h)
	s.renderDetail(true)
}

func (s *projects) inDetail() bool { return s.detailSlug != "" }

func (s *projects) Back() bool {
	if s.inDetail() {
		if s.read.close() {
			return true
		}
		s.detailSlug, s.detail = "", nil
		s.dload.done(nil)
		return true
	}
	if s.list.FilterState() != list.Unfiltered {
		s.list.ResetFilter()
		return true
	}
	return false
}

func (s *projects) Capturing() bool { return !s.inDetail() && s.list.SettingFilter() }

func (s *projects) Crumb() string {
	if s.inDetail() {
		if s.detail != nil {
			return s.detail.Title
		}
		return s.detailSlug
	}
	if s.typ > 0 {
		return "type: " + s.types[s.typ]
	}
	return ""
}

func (s *projects) Keys() []key.Binding {
	if s.inDetail() {
		return append(s.read.keys(), key.NewBinding(key.WithKeys("esc"), key.WithHelp("q/esc", "back to list")))
	}
	return append(listKeys(),
		key.NewBinding(key.WithKeys("left", "right"), key.WithHelp("←/→ h/l", "filter by type")),
		keyRefresh)
}

func (s *projects) Update(msg tea.Msg) tea.Cmd {
	if cmd := s.load.update(msg); cmd != nil {
		return cmd
	}
	if cmd := s.dload.update(msg); cmd != nil {
		return cmd
	}
	switch msg := msg.(type) {
	case projectsMsg:
		s.load.done(msg.err)
		if msg.err == nil {
			s.loaded = true
			s.all = msg.items
			s.buildTypes()
			return s.applyFilter()
		}
		return nil
	case projectMsg:
		if msg.slug != s.detailSlug {
			return nil
		}
		s.dload.done(msg.err)
		if msg.err == nil {
			p := msg.p
			s.detail = &p
			s.renderDetail(false)
		}
		return nil
	case themeChangedMsg:
		styleList(&s.list, s.env)
		s.renderDetail(true)
		return nil
	case tea.KeyPressMsg:
		if s.inDetail() {
			if msg.String() == "r" && s.dload.err != nil {
				return s.open(s.detailSlug)
			}
			return s.read.update(msg)
		}
		if !s.list.SettingFilter() {
			switch msg.String() {
			case "left", "h":
				s.typ = (s.typ - 1 + len(s.types)) % len(s.types)
				return s.applyFilter()
			case "right", "l":
				s.typ = (s.typ + 1) % len(s.types)
				return s.applyFilter()
			case "r":
				if !s.load.loading {
					return s.fetch()
				}
				return nil
			case "enter":
				if it, ok := s.list.SelectedItem().(entry); ok {
					return s.open(it.value.(api.ProjectSummary).Slug)
				}
				return nil
			}
		}
		var cmd tea.Cmd
		s.list, cmd = s.list.Update(msg)
		return cmd
	case tea.MouseWheelMsg:
		if s.inDetail() {
			return s.read.update(msg)
		}
		wheel(&s.list, msg)
		return nil
	}
	if !s.inDetail() {
		var cmd tea.Cmd
		s.list, cmd = s.list.Update(msg)
		return cmd
	}
	return nil
}

func (s *projects) buildTypes() {
	seen := map[string]bool{}
	types := []string{}
	for _, p := range s.all {
		if p.Type != "" && !seen[p.Type] {
			seen[p.Type] = true
			types = append(types, p.Type)
		}
	}
	sort.Strings(types)
	prev := s.types[min(s.typ, len(s.types)-1)]
	s.types = append([]string{"all"}, types...)
	s.typ = 0
	for i, ty := range s.types {
		if ty == prev {
			s.typ = i
		}
	}
}

// visibleProjects applies the type filter (exported for tests via the
// package).
func (s *projects) visibleProjects() []api.ProjectSummary {
	if s.typ == 0 {
		return s.all
	}
	var out []api.ProjectSummary
	for _, p := range s.all {
		if p.Type == s.types[s.typ] {
			out = append(out, p)
		}
	}
	return out
}

func (s *projects) applyFilter() tea.Cmd {
	items := []list.Item{}
	for _, p := range s.visibleProjects() {
		tech := make([]string, 0, len(p.Technologies))
		for _, tch := range p.Technologies {
			tech = append(tech, tch.Name)
		}
		meta := []string{}
		if p.Type != "" {
			meta = append(meta, p.Type)
		}
		if p.Status != "" {
			meta = append(meta, p.Status)
		}
		if len(tech) > 0 {
			meta = append(meta, strings.Join(tech, ", "))
		}
		badge := ""
		if p.IsFeatured {
			badge = "★"
		}
		items = append(items, entry{
			title:  p.Title,
			desc:   orDefault(p.Summary, "—"),
			meta:   strings.Join(meta, " · "),
			badge:  badge,
			value:  p,
			filter: p.Title + " " + p.Summary + " " + strings.Join(tech, " ") + " " + p.Type,
		})
	}
	s.list.ResetSelected()
	return s.list.SetItems(items)
}

func (s *projects) renderDetail(keep bool) {
	if s.detail == nil || s.w == 0 {
		return
	}
	t := s.env.Theme
	p := s.detail
	width := min(s.w-4, 100)

	var b strings.Builder
	b.WriteString(t.Comment.Render("// projects/"+p.Slug) + "\n")
	b.WriteString(t.Title.Render(p.Title) + "\n")
	meta := []string{}
	if p.Type != "" {
		meta = append(meta, t.Keyword.Render(p.Type))
	}
	if p.Status != "" {
		meta = append(meta, t.Accent.Render(p.Status))
	}
	end := p.EndDate
	if p.IsOngoing {
		end = ""
	}
	if p.StartDate != "" {
		meta = append(meta, t.Faint.Render(components.Range(p.StartDate, end, components.MonthYear)))
	}
	if len(meta) > 0 {
		b.WriteString(strings.Join(meta, t.Faint.Render(" · ")) + "\n")
	}
	if p.Summary != "" {
		b.WriteString("\n" + t.Body.Render(xansi.Wordwrap(p.Summary, width, "")) + "\n")
	}
	if len(p.Technologies) > 0 {
		b.WriteString("\n" + techTags(t, p.Technologies, width) + "\n")
	}
	links := [][2]string{{"url", p.URL}, {"github", p.GithubURL}, {"demo", p.DemoURL}, {"web", p.WebURL}}
	var lb strings.Builder
	for _, l := range links {
		if l[1] == "" {
			continue
		}
		lb.WriteString(t.Keyword.Render(fmt.Sprintf("  %-7s", l[0])) + t.Link.Hyperlink(l[1]).Render(l[1]) + "\n")
	}
	if lb.Len() > 0 {
		b.WriteString("\n" + t.Comment.Render("// links") + "\n" + lb.String())
	}
	header := b.String()

	md := p.Markdown
	if strings.TrimSpace(md) == "" {
		md = p.Text
	}
	body := ""
	if strings.TrimSpace(md) != "" {
		body = components.RenderMarkdown(md, width, t.IsDark)
	}
	if len(p.Gallery) > 0 {
		body += "\n\n" + t.Comment.Render(fmt.Sprintf("// gallery: %d %s, view on the web at ", len(p.Gallery), plural(len(p.Gallery), "image", "images"))) +
			t.Link.Render(orDefault(p.WebURL, s.env.SiteURL))
	}
	content := header + "\n" + body
	headerLines := strings.Count(header, "\n") + 1
	toc := components.LocateHeadings(content, components.TOC(md), headerLines)
	s.read.setContent(lipgloss.NewStyle().Padding(0, 2).Render(content), toc, keep)
}

func techTags(t *theme.Theme, techs []api.Technology, width int) string {
	var lines []string
	line := ""
	for _, tch := range techs {
		c := theme.Hex(tch.Color, t.Primary)
		tag := lipgloss.NewStyle().Foreground(c).Render("● ") + t.Body.Render(tch.Name)
		if line != "" && lipgloss.Width(line)+lipgloss.Width(tag)+3 > width {
			lines = append(lines, line)
			line = ""
		}
		if line != "" {
			line += "   "
		}
		line += tag
	}
	if line != "" {
		lines = append(lines, line)
	}
	return strings.Join(lines, "\n")
}

func (s *projects) View() string {
	if s.inDetail() {
		if v := s.dload.view("project", s.w, s.h); v != "" {
			return v
		}
		return s.read.view()
	}
	if v := s.load.view("projects", s.w, s.h); v != "" {
		return v
	}
	tabs := " " + s.env.Theme.Faint.Render("type ") + renderTabs(s.env, s.types, s.typ, s.w-8)
	return tabs + "\n\n" + s.list.View()
}

func plural(n int, one, many string) string {
	if n == 1 {
		return one
	}
	return many
}

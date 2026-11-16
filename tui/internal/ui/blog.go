package ui

import (
	"fmt"
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

type blogCategoriesMsg struct {
	cats []api.Category
	err  error
}

type postsMsg struct {
	category string
	posts    []api.PostSummary
	err      error
}

type postMsg struct {
	slug string
	post api.Post
	err  error
}

type blog struct {
	env  *Env
	w, h int

	cats    []api.Category // index 0 = "all" (zero value)
	cat     int
	load    loader
	loaded  string // category slug currently shown; "\x00" = none yet
	list    list.Model
	started bool

	detail     *api.Post
	detailSlug string
	dload      loader
	read       reader
}

func newBlog(env *Env) *blog {
	return &blog{
		env:    env,
		cats:   []api.Category{{Name: "all"}},
		load:   newLoader(env),
		dload:  newLoader(env),
		list:   newList(env, "post", "posts"),
		read:   newReader(env),
		loaded: "\x00",
	}
}

func (s *blog) Activate() tea.Cmd {
	if s.started {
		return nil
	}
	s.started = true
	return tea.Batch(s.fetchPosts(), func() tea.Msg {
		cats, err := s.env.Client.BlogCategories(s.env.Ctx)
		return blogCategoriesMsg{cats, err}
	})
}

func (s *blog) currentSlug() string { return s.cats[s.cat].Slug }

func (s *blog) fetchPosts() tea.Cmd {
	slug := s.currentSlug()
	return tea.Batch(s.load.start(), func() tea.Msg {
		posts, err := s.env.Client.Posts(s.env.Ctx, api.PostQuery{Category: slug})
		return postsMsg{slug, posts, err}
	})
}

func (s *blog) open(slug string) tea.Cmd {
	s.detailSlug, s.detail = slug, nil
	return tea.Batch(s.dload.start(), func() tea.Msg {
		p, err := s.env.Client.Post(s.env.Ctx, slug)
		return postMsg{slug, p, err}
	})
}

func (s *blog) SetSize(w, h int) {
	s.w, s.h = w, h
	s.list.SetSize(w, max(h-2, 1))
	s.read.setSize(w, h)
	s.renderDetail(true)
}

func (s *blog) inDetail() bool { return s.detailSlug != "" }

func (s *blog) Back() bool {
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

func (s *blog) Capturing() bool { return !s.inDetail() && s.list.SettingFilter() }

func (s *blog) Crumb() string {
	if s.inDetail() {
		if s.detail != nil {
			return s.detail.Title
		}
		return s.detailSlug
	}
	if s.cat > 0 {
		return "category: " + s.cats[s.cat].Name
	}
	return ""
}

func (s *blog) Keys() []key.Binding {
	if s.inDetail() {
		return append(s.read.keys(), key.NewBinding(key.WithKeys("esc"), key.WithHelp("q/esc", "back to posts")))
	}
	return append(listKeys(),
		key.NewBinding(key.WithKeys("left", "right"), key.WithHelp("←/→ h/l", "category")),
		keyRefresh)
}

func (s *blog) Update(msg tea.Msg) tea.Cmd {
	if cmd := s.load.update(msg); cmd != nil {
		return cmd
	}
	if cmd := s.dload.update(msg); cmd != nil {
		return cmd
	}
	switch msg := msg.(type) {
	case blogCategoriesMsg:
		if msg.err == nil {
			prev := s.currentSlug()
			s.cats = append([]api.Category{{Name: "all"}}, msg.cats...)
			s.cat = 0
			for i, c := range s.cats {
				if c.Slug == prev {
					s.cat = i
				}
			}
		}
		return nil
	case postsMsg:
		if msg.category != s.currentSlug() {
			return nil // stale response for a category we've left
		}
		s.load.done(msg.err)
		if msg.err == nil {
			s.loaded = msg.category
			return s.setPosts(msg.posts)
		}
		return nil
	case postMsg:
		if msg.slug != s.detailSlug {
			return nil
		}
		s.dload.done(msg.err)
		if msg.err == nil {
			p := msg.post
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
				return s.selectCategory(s.cat - 1)
			case "right", "l":
				return s.selectCategory(s.cat + 1)
			case "r":
				if !s.load.loading {
					return s.fetchPosts()
				}
				return nil
			case "enter":
				if it, ok := s.list.SelectedItem().(entry); ok {
					return s.open(it.value.(api.PostSummary).Slug)
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

func (s *blog) selectCategory(i int) tea.Cmd {
	n := len(s.cats)
	s.cat = (i + n) % n
	s.list.ResetFilter()
	return s.fetchPosts()
}

func (s *blog) setPosts(posts []api.PostSummary) tea.Cmd {
	items := make([]list.Item, 0, len(posts))
	for _, p := range posts {
		meta := []string{}
		if d := components.Date(p.PublishedAt.Time); d != "" {
			meta = append(meta, d)
		}
		if p.ReadingTime > 0 {
			meta = append(meta, fmt.Sprintf("%d min read", p.ReadingTime))
		}
		if p.Category != nil {
			meta = append(meta, p.Category.Name)
		}
		if p.Series != nil {
			meta = append(meta, fmt.Sprintf("%s #%d", p.Series.Title, p.Series.Order))
		}
		for _, tag := range p.Tags {
			meta = append(meta, "#"+tag)
		}
		badge := ""
		if p.IsFeatured {
			badge = "★ featured"
		}
		items = append(items, entry{
			title: p.Title,
			desc:  orDefault(p.Excerpt, "—"),
			meta:  strings.Join(meta, " · "),
			badge: badge,
			value: p,
		})
	}
	s.list.ResetSelected()
	return s.list.SetItems(items)
}

func (s *blog) renderDetail(keep bool) {
	if s.detail == nil || s.w == 0 {
		return
	}
	t := s.env.Theme
	p := s.detail
	width := min(s.w-4, 100)

	var b strings.Builder
	b.WriteString(t.Comment.Render("// blog/"+p.Slug) + "\n")
	b.WriteString(t.Title.Render(xansi.Wordwrap(p.Title, width, "")) + "\n")
	b.WriteString(postMeta(t, p.PostSummary) + "\n")
	if len(p.Tags) > 0 {
		tags := make([]string, len(p.Tags))
		for i, tag := range p.Tags {
			tags[i] = t.Keyword.Render("#" + tag)
		}
		b.WriteString(strings.Join(tags, " ") + "\n")
	}
	b.WriteString(lipgloss.NewStyle().Foreground(t.Border).Render(strings.Repeat("─", width)) + "\n")
	header := b.String()

	md := p.Markdown
	if strings.TrimSpace(md) == "" {
		md = p.Text
	}
	body := components.RenderMarkdown(md, width, t.IsDark)
	footer := "\n\n" + t.Comment.Render("// read it on the web: ") + t.Link.Hyperlink(p.URL).Render(p.URL)
	content := header + "\n" + body + footer
	toc := components.LocateHeadings(content, components.TOC(md), strings.Count(header, "\n")+1)
	s.read.setContent(lipgloss.NewStyle().Padding(0, 2).Render(content), toc, keep)
}

func postMeta(t *theme.Theme, p api.PostSummary) string {
	parts := []string{}
	if d := components.Date(p.PublishedAt.Time); d != "" {
		parts = append(parts, t.Accent.Render(d))
	}
	if p.ReadingTime > 0 {
		parts = append(parts, t.Faint.Render(fmt.Sprintf("%d min read", p.ReadingTime)))
	}
	if p.Category != nil {
		parts = append(parts, lipgloss.NewStyle().Foreground(theme.Hex(p.Category.Color, t.Primary)).Render(p.Category.Name))
	}
	if p.Series != nil {
		parts = append(parts, t.Keyword.Render(fmt.Sprintf("%s · part %d", p.Series.Title, p.Series.Order)))
	}
	return strings.Join(parts, t.Faint.Render(" · "))
}

func (s *blog) View() string {
	if s.inDetail() {
		if v := s.dload.view("post", s.w, s.h); v != "" {
			return v
		}
		return s.read.view()
	}
	names := make([]string, len(s.cats))
	for i, c := range s.cats {
		names[i] = strings.ToLower(c.Name)
	}
	tabs := " " + renderTabs(s.env, names, s.cat, s.w-2)
	if v := s.load.view("posts", s.w, s.h-2); v != "" {
		return tabs + "\n\n" + v
	}
	return tabs + "\n\n" + s.list.View()
}

package ui

import (
	"fmt"
	"image"
	"strings"

	"charm.land/bubbles/v2/key"
	"charm.land/bubbles/v2/list"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/ui/components"
)

type photoCategoriesMsg struct {
	cats []api.PhotoCategory
	err  error
}

type photosMsg struct {
	category string
	photos   []api.Photo
	err      error
}

type photoMsg struct {
	slug  string
	photo api.PhotoDetail
	err   error
}

type thumbMsg struct {
	slug string
	img  image.Image
	err  error
}

type photos struct {
	env  *Env
	w, h int

	cats    []api.PhotoCategory
	cat     int
	load    loader
	list    list.Model
	started bool

	detail     *api.PhotoDetail
	detailSlug string
	opened     api.Photo
	dload      loader
	thumb      image.Image
	thumbErr   error
	thumbBusy  bool
	read       reader
}

func newPhotos(env *Env) *photos {
	return &photos{
		env:   env,
		cats:  []api.PhotoCategory{{Name: "all"}},
		load:  newLoader(env),
		dload: newLoader(env),
		list:  newList(env, "photo", "photos"),
		read:  newReader(env),
	}
}

func (s *photos) Activate() tea.Cmd {
	if s.started {
		return nil
	}
	s.started = true
	return tea.Batch(s.fetch(), func() tea.Msg {
		cats, err := s.env.Client.PhotoCategories(s.env.Ctx)
		return photoCategoriesMsg{cats, err}
	})
}

func (s *photos) currentSlug() string { return s.cats[s.cat].Slug }

func (s *photos) fetch() tea.Cmd {
	slug := s.currentSlug()
	return tea.Batch(s.load.start(), func() tea.Msg {
		ps, err := s.env.Client.Photos(s.env.Ctx, api.PhotoQuery{Category: slug})
		return photosMsg{slug, ps, err}
	})
}

func (s *photos) open(p api.Photo) tea.Cmd {
	s.detailSlug, s.detail, s.opened = p.Slug, nil, p
	s.thumb, s.thumbErr, s.thumbBusy = nil, nil, p.ThumbnailURL != ""
	cmds := []tea.Cmd{s.dload.start(), func() tea.Msg {
		d, err := s.env.Client.Photo(s.env.Ctx, p.Slug)
		return photoMsg{p.Slug, d, err}
	}}
	if p.ThumbnailURL != "" {
		cmds = append(cmds, s.fetchThumb(p.Slug, p.ThumbnailURL))
	}
	return tea.Batch(cmds...)
}

func (s *photos) fetchThumb(slug, url string) tea.Cmd {
	return func() tea.Msg {
		b, err := s.env.Client.Image(s.env.Ctx, url)
		if err != nil {
			return thumbMsg{slug: slug, err: err}
		}
		img, err := components.DecodeImage(b)
		return thumbMsg{slug, img, err}
	}
}

func (s *photos) SetSize(w, h int) {
	s.w, s.h = w, h
	s.list.SetSize(w, max(h-2, 1))
	s.read.setSize(w, h)
	s.renderDetail(true)
}

func (s *photos) inDetail() bool { return s.detailSlug != "" }

func (s *photos) Back() bool {
	if s.inDetail() {
		s.detailSlug, s.detail, s.thumb = "", nil, nil
		s.dload.done(nil)
		return true
	}
	if s.list.FilterState() != list.Unfiltered {
		s.list.ResetFilter()
		return true
	}
	return false
}

func (s *photos) Capturing() bool { return !s.inDetail() && s.list.SettingFilter() }

func (s *photos) Crumb() string {
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

func (s *photos) Keys() []key.Binding {
	if s.inDetail() {
		return append(s.read.keys(), key.NewBinding(key.WithKeys("esc"), key.WithHelp("q/esc", "back to photos")))
	}
	return append(listKeys(),
		key.NewBinding(key.WithKeys("left", "right"), key.WithHelp("←/→ h/l", "category")),
		keyRefresh)
}

func (s *photos) Update(msg tea.Msg) tea.Cmd {
	if cmd := s.load.update(msg); cmd != nil {
		return cmd
	}
	if cmd := s.dload.update(msg); cmd != nil {
		return cmd
	}
	switch msg := msg.(type) {
	case photoCategoriesMsg:
		if msg.err == nil {
			prev := s.currentSlug()
			s.cats = append([]api.PhotoCategory{{Name: "all"}}, msg.cats...)
			s.cat = 0
			for i, c := range s.cats {
				if c.Slug == prev {
					s.cat = i
				}
			}
		}
		return nil
	case photosMsg:
		if msg.category != s.currentSlug() {
			return nil
		}
		s.load.done(msg.err)
		if msg.err == nil {
			return s.setPhotos(msg.photos)
		}
		return nil
	case photoMsg:
		if msg.slug != s.detailSlug {
			return nil
		}
		s.dload.done(msg.err)
		if msg.err == nil {
			d := msg.photo
			s.detail = &d
			s.renderDetail(false)
		}
		return nil
	case thumbMsg:
		if msg.slug != s.detailSlug {
			return nil
		}
		s.thumb, s.thumbErr, s.thumbBusy = msg.img, msg.err, false
		s.renderDetail(true)
		return nil
	case themeChangedMsg:
		styleList(&s.list, s.env)
		s.renderDetail(true)
		return nil
	case tea.KeyPressMsg:
		if s.inDetail() {
			if msg.String() == "r" && s.dload.err != nil {
				return s.open(s.opened)
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
					return s.fetch()
				}
				return nil
			case "enter":
				if it, ok := s.list.SelectedItem().(entry); ok {
					return s.open(it.value.(api.Photo))
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

func (s *photos) selectCategory(i int) tea.Cmd {
	n := len(s.cats)
	s.cat = (i + n) % n
	s.list.ResetFilter()
	return s.fetch()
}

func (s *photos) setPhotos(ps []api.Photo) tea.Cmd {
	items := make([]list.Item, 0, len(ps))
	for _, p := range ps {
		meta := []string{}
		if p.Category != nil {
			meta = append(meta, p.Category.Name)
		}
		if d := components.Date(p.TakenAt.Time); d != "" {
			meta = append(meta, d)
		}
		if p.Width > 0 && p.Height > 0 {
			meta = append(meta, fmt.Sprintf("%d×%d", p.Width, p.Height))
		}
		badges := []string{}
		if p.IsFavorite {
			badges = append(badges, "★")
		}
		if p.PrintsAvailable {
			badges = append(badges, "prints")
		}
		items = append(items, entry{
			title:  p.Title,
			desc:   orDefault(p.ExifSummary, exifLine(p)),
			meta:   strings.Join(meta, " · "),
			badge:  strings.Join(badges, " "),
			value:  p,
			filter: p.Title + " " + p.ExifSummary + " " + strings.Join(p.Tags, " "),
		})
	}
	s.list.ResetSelected()
	return s.list.SetItems(items)
}

func exifLine(p api.Photo) string {
	parts := []string{}
	for _, v := range []api.Flex{p.CameraBody, p.FocalLength, p.Aperture, p.ShutterSpeed} {
		if v != "" {
			parts = append(parts, v.String())
		}
	}
	if p.ISO != "" {
		parts = append(parts, "ISO "+p.ISO.String())
	}
	if len(parts) == 0 {
		return "no EXIF data"
	}
	return strings.Join(parts, " · ")
}

func (s *photos) renderDetail(keep bool) {
	if s.detail == nil || s.w == 0 {
		return
	}
	t := s.env.Theme
	p := s.detail
	width := min(s.w-4, 100)

	var b strings.Builder
	b.WriteString(t.Comment.Render("// photography/"+p.Slug) + "\n\n")

	switch {
	case s.thumb != nil:
		if img := components.HalfBlock(s.thumb, width, max((s.h-1)*2/3, 6), s.env.Profile); img != "" {
			b.WriteString(img + "\n\n")
		} else {
			b.WriteString(t.Faint.Render("[image preview needs a colour terminal]") + "\n\n")
		}
	case s.thumbBusy:
		b.WriteString(t.Faint.Render("rendering preview…") + "\n\n")
	case s.thumbErr != nil:
		b.WriteString(t.Faint.Render("[preview unavailable in the terminal, see it on the web]") + "\n\n")
	}

	b.WriteString(t.Title.Render(p.Title))
	if p.IsFavorite {
		b.WriteString(t.Accent.Render("  ★ favourite"))
	}
	b.WriteString("\n")
	meta := []string{}
	if p.Category != nil {
		meta = append(meta, t.Ident.Render(p.Category.Name))
	}
	if d := components.Date(p.TakenAt.Time); d != "" {
		meta = append(meta, t.Accent.Render(d))
	}
	if p.Width > 0 {
		meta = append(meta, t.Faint.Render(fmt.Sprintf("%d×%d px", p.Width, p.Height)))
	}
	if len(meta) > 0 {
		b.WriteString(strings.Join(meta, t.Faint.Render(" · ")) + "\n")
	}
	if desc := strings.TrimSpace(p.Text); desc != "" {
		b.WriteString("\n" + t.Body.Render(xansi.Wordwrap(desc, width, "")) + "\n")
	}

	exif := [][2]string{
		{"camera", p.CameraBody.String()}, {"lens", p.Lens.String()}, {"focal", p.FocalLength.String()},
		{"aperture", p.Aperture.String()}, {"shutter", p.ShutterSpeed.String()}, {"iso", p.ISO.String()},
	}
	var eb strings.Builder
	for _, kv := range exif {
		if kv[1] == "" {
			continue
		}
		eb.WriteString("  " + t.Keyword.Render(fmt.Sprintf("%-9s", kv[0])) + t.Body.Render(kv[1]) + "\n")
	}
	if eb.Len() > 0 {
		b.WriteString("\n" + t.Comment.Render("// exif") + "\n" + eb.String())
	}
	if len(p.Tags) > 0 {
		tags := make([]string, len(p.Tags))
		for i, tag := range p.Tags {
			tags[i] = t.Keyword.Render("#" + tag)
		}
		b.WriteString("\n" + strings.Join(tags, " ") + "\n")
	}

	web := orDefault(p.WebURL, p.URL)
	b.WriteString("\n" + t.Comment.Render("// view full size") + "\n  " + t.Link.Hyperlink(web).Render(web) + "\n")

	if len(p.PrintOptions) > 0 {
		b.WriteString("\n" + t.Comment.Render("// prints") + "\n")
		nameW, sizeW := 0, 0
		for _, o := range p.PrintOptions {
			nameW = max(nameW, lipgloss.Width(o.Name))
			sizeW = max(sizeW, lipgloss.Width(o.SizeLabel))
		}
		for _, o := range p.PrintOptions {
			name := o.Name + strings.Repeat(" ", nameW-lipgloss.Width(o.Name))
			size := o.SizeLabel + strings.Repeat(" ", sizeW-lipgloss.Width(o.SizeLabel))
			line := "  " + t.Body.Render(name) + "  " + t.Faint.Render(size) + "  " +
				t.Success.Render(fmt.Sprintf("%10s", components.Money(o.Price, o.Currency)))
			b.WriteString(line + "\n")
			if o.Description != "" {
				b.WriteString("    " + t.Faint.Render(xansi.Truncate(o.Description, width-4, "…")) + "\n")
			}
		}
		b.WriteString("\n  " + t.Accent.Render("order a print on the web: ") + t.Link.Hyperlink(web).Render(web) + "\n")
	} else if p.PrintsAvailable {
		b.WriteString("\n" + t.Comment.Render("// prints available at ") + t.Link.Render(web) + "\n")
	}

	s.read.setContent(lipgloss.NewStyle().Padding(0, 2).Render(b.String()), nil, keep)
}

func (s *photos) View() string {
	if s.inDetail() {
		if v := s.dload.view("photo", s.w, s.h); v != "" {
			return v
		}
		return s.read.view()
	}
	names := make([]string, len(s.cats))
	for i, c := range s.cats {
		names[i] = strings.ToLower(c.Name)
	}
	tabs := " " + renderTabs(s.env, names, s.cat, s.w-2)
	if v := s.load.view("photos", s.w, s.h-2); v != "" {
		return tabs + "\n\n" + v
	}
	return tabs + "\n\n" + s.list.View()
}

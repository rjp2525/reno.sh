package ui

import (
	"fmt"
	"io"
	"strings"

	"charm.land/bubbles/v2/key"
	"charm.land/bubbles/v2/list"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"
)

// entry is a generic list row.
type entry struct {
	title  string
	desc   string
	meta   string
	badge  string // short right-aligned marker (★, prints, …)
	filter string
	value  any
}

func (e entry) FilterValue() string {
	if e.filter != "" {
		return e.filter
	}
	return e.title
}

// delegate renders entries in the editor style:
//
//	▸ Title                              ★
//	  description
//	  meta · meta
type delegate struct{ env *Env }

func (d delegate) Height() int                         { return 3 }
func (d delegate) Spacing() int                        { return 1 }
func (d delegate) Update(tea.Msg, *list.Model) tea.Cmd { return nil }

func (d delegate) Render(w io.Writer, m list.Model, index int, item list.Item) {
	e, ok := item.(entry)
	if !ok {
		return
	}
	t := d.env.Theme
	width := max(m.Width()-4, 10)
	selected := index == m.Index()
	dimmed := m.FilterState() == list.Filtering && m.FilterValue() == ""

	titleStyle := t.Body
	gutter := "  "
	if selected && !dimmed {
		titleStyle = t.Selected
		gutter = t.Accent.Render("▸ ")
	} else if dimmed {
		titleStyle = t.Faint
	}

	title := xansi.Truncate(e.title, width-lipgloss.Width(e.badge)-2, "…")
	if matches := m.MatchesForItem(index); len(matches) > 0 && e.filter == "" {
		title = lipgloss.StyleRunes(title, matches, titleStyle.Underline(true), titleStyle)
	} else {
		title = titleStyle.Render(title)
	}
	if e.badge != "" {
		pad := width - lipgloss.Width(title) - lipgloss.Width(e.badge)
		title += strings.Repeat(" ", max(pad, 1)) + t.Accent.Render(e.badge)
	}
	desc := t.Faint.Render(xansi.Truncate(e.desc, width, "…"))
	meta := t.Ident.Render(xansi.Truncate(e.meta, width, "…"))
	if dimmed {
		meta = t.Faint.Render(xansi.Truncate(e.meta, width, "…"))
	}
	fmt.Fprintf(w, "%s%s\n  %s\n  %s", gutter, title, desc, meta)
}

// newList builds a list styled for the app with filtering on.
func newList(env *Env, noun, nouns string) list.Model {
	l := list.New(nil, delegate{env: env}, 0, 0)
	l.SetShowTitle(false)
	l.SetShowHelp(false)
	l.SetShowPagination(true)
	l.SetStatusBarItemName(noun, nouns)
	l.DisableQuitKeybindings()
	l.KeyMap.NextPage = key.NewBinding(key.WithKeys("pgdown", "ctrl+f"), key.WithHelp("pgdn", "next page"))
	l.KeyMap.PrevPage = key.NewBinding(key.WithKeys("pgup", "ctrl+b"), key.WithHelp("pgup", "prev page"))
	styleList(&l, env)
	return l
}

func styleList(l *list.Model, env *Env) {
	t := env.Theme
	s := list.DefaultStyles(t.IsDark)
	s.TitleBar = lipgloss.NewStyle().Padding(0, 0, 0, 2)
	s.StatusBar = lipgloss.NewStyle().Foreground(t.Muted).Padding(0, 0, 1, 2)
	s.StatusBarActiveFilter = lipgloss.NewStyle().Foreground(t.Orange)
	s.StatusBarFilterCount = lipgloss.NewStyle().Foreground(t.Muted)
	s.NoItems = lipgloss.NewStyle().Foreground(t.Muted).Padding(0, 2)
	s.ActivePaginationDot = lipgloss.NewStyle().Foreground(t.Primary).SetString("•")
	s.InactivePaginationDot = lipgloss.NewStyle().Foreground(t.Border).SetString("•")
	s.Filter.Focused.Prompt = lipgloss.NewStyle().Foreground(t.Orange)
	s.Filter.Focused.Text = lipgloss.NewStyle().Foreground(t.Bright)
	s.Filter.Blurred.Prompt = lipgloss.NewStyle().Foreground(t.Muted)
	s.Filter.Cursor.Color = t.Primary
	l.Styles = s
	l.FilterInput.Prompt = "/ "
	l.FilterInput.SetStyles(s.Filter)
}

// wheel turns a mouse wheel event into list cursor movement.
func wheel(l *list.Model, msg tea.MouseWheelMsg) {
	switch msg.Button {
	case tea.MouseWheelUp:
		l.CursorUp()
	case tea.MouseWheelDown:
		l.CursorDown()
	}
}

// listKeys are the shared contextual bindings for list screens.
func listKeys() []key.Binding {
	return []key.Binding{
		key.NewBinding(key.WithKeys("up", "k"), key.WithHelp("↑/k", "up")),
		key.NewBinding(key.WithKeys("down", "j"), key.WithHelp("↓/j", "down")),
		key.NewBinding(key.WithKeys("enter"), key.WithHelp("enter", "open")),
		key.NewBinding(key.WithKeys("/"), key.WithHelp("/", "fuzzy search")),
	}
}

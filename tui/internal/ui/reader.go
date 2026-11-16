package ui

import (
	"fmt"
	"strings"

	"charm.land/bubbles/v2/key"
	"charm.land/bubbles/v2/viewport"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/ui/components"
)

// reader is a scrollable document view with a reading-progress line and an
// optional table-of-contents jump overlay.
type reader struct {
	env       *Env
	vp        viewport.Model
	toc       []components.Heading
	tocOpen   bool
	tocCursor int
	w, h      int
}

func newReader(env *Env) reader {
	vp := viewport.New()
	vp.MouseWheelEnabled = true
	vp.MouseWheelDelta = 3
	return reader{env: env, vp: vp}
}

func (r *reader) setSize(w, h int) {
	r.w, r.h = w, h
	r.vp.SetWidth(w)
	r.vp.SetHeight(max(h-1, 1))
}

// setContent replaces the document, keeping the scroll position if asked.
func (r *reader) setContent(s string, toc []components.Heading, keepOffset bool) {
	off := r.vp.YOffset()
	r.vp.SetContent(s)
	r.toc = toc
	if keepOffset {
		r.vp.SetYOffset(off)
	} else {
		r.vp.GotoTop()
	}
	r.tocCursor = min(r.tocCursor, max(len(r.toc)-1, 0))
}

func (r *reader) hasTOC() bool {
	for _, h := range r.toc {
		if h.Line >= 0 {
			return true
		}
	}
	return false
}

func (r *reader) keys() []key.Binding {
	ks := []key.Binding{
		key.NewBinding(key.WithKeys("j", "k"), key.WithHelp("j/k ↑/↓", "scroll")),
		key.NewBinding(key.WithKeys("space"), key.WithHelp("space/b", "page down/up")),
		key.NewBinding(key.WithKeys("d", "u"), key.WithHelp("d/u", "half page")),
		key.NewBinding(key.WithKeys("g", "G"), key.WithHelp("g/G", "top/bottom")),
	}
	if r.hasTOC() {
		ks = append(ks, key.NewBinding(key.WithKeys("t"), key.WithHelp("t", "table of contents")))
	}
	return ks
}

// close closes the TOC overlay; it reports whether it was open.
func (r *reader) close() bool {
	if r.tocOpen {
		r.tocOpen = false
		return true
	}
	return false
}

func (r *reader) update(msg tea.Msg) tea.Cmd {
	switch msg := msg.(type) {
	case tea.KeyPressMsg:
		if r.tocOpen {
			r.updateTOC(msg)
			return nil
		}
		switch msg.String() {
		case "t":
			if r.hasTOC() {
				r.tocOpen = true
				// Preselect the section we're currently reading.
				for i, h := range r.toc {
					if h.Line >= 0 && h.Line <= r.vp.YOffset() {
						r.tocCursor = i
					}
				}
			}
			return nil
		case "g", "home":
			r.vp.GotoTop()
			return nil
		case "G", "end":
			r.vp.GotoBottom()
			return nil
		case "space":
			r.vp.PageDown()
			return nil
		case "b":
			r.vp.PageUp()
			return nil
		}
		var cmd tea.Cmd
		r.vp, cmd = r.vp.Update(msg)
		return cmd
	case tea.MouseWheelMsg:
		if r.tocOpen {
			if msg.Button == tea.MouseWheelUp {
				r.tocMove(-1)
			} else if msg.Button == tea.MouseWheelDown {
				r.tocMove(1)
			}
			return nil
		}
		var cmd tea.Cmd
		r.vp, cmd = r.vp.Update(msg)
		return cmd
	}
	return nil
}

func (r *reader) tocMove(d int) {
	n := len(r.toc)
	for i := 0; i < n; i++ {
		r.tocCursor = (r.tocCursor + d + n) % n
		if r.toc[r.tocCursor].Line >= 0 {
			return
		}
	}
}

func (r *reader) updateTOC(msg tea.KeyPressMsg) {
	switch msg.String() {
	case "up", "k":
		r.tocMove(-1)
	case "down", "j":
		r.tocMove(1)
	case "enter", "space":
		if h := r.toc[r.tocCursor]; h.Line >= 0 {
			r.vp.SetYOffset(h.Line)
		}
		r.tocOpen = false
	case "t":
		r.tocOpen = false
	}
}

func (r *reader) view() string {
	t := r.env.Theme
	body := r.vp.View()
	if r.tocOpen {
		body = r.tocView()
	}
	pct := r.vp.ScrollPercent()
	if r.vp.TotalLineCount() <= r.vp.Height() {
		pct = 1
	}
	hint := ""
	if r.hasTOC() {
		hint = t.Faint.Render("  t contents")
	}
	barW := max(r.w-lipgloss.Width(hint)-2, 10)
	progress := " " + components.Progress(pct, barW, t.Primary, t.Border) + hint
	return body + "\n" + xansi.Truncate(progress, r.w, "")
}

func (r *reader) tocView() string {
	t := r.env.Theme
	var lines []string
	for i, h := range r.toc {
		if h.Line < 0 {
			continue
		}
		indent := strings.Repeat("  ", max(h.Level-2, 0))
		label := indent + h.Title
		if i == r.tocCursor {
			lines = append(lines, t.Accent.Render("▸ ")+t.Selected.Render(label))
		} else {
			lines = append(lines, "  "+t.Body.Render(label))
		}
	}
	maxW := max(min(r.w-8, 70), 20)
	maxLines := max(r.h-8, 3)
	// Scroll the list so the cursor stays visible.
	cur := 0
	for i, h := range r.toc {
		if h.Line >= 0 && i <= r.tocCursor {
			cur++
		}
	}
	start := max(0, min(cur-maxLines, len(lines)-maxLines))
	if len(lines) > maxLines {
		lines = lines[start : start+maxLines]
	}
	for i := range lines {
		lines[i] = xansi.Truncate(lines[i], maxW, "…")
	}
	box := t.Box.BorderForeground(t.Primary).Padding(0, 2).Render(
		t.Heading.Render("contents") + t.Faint.Render(fmt.Sprintf("  %d sections", len(r.toc))) + "\n\n" +
			strings.Join(lines, "\n") + "\n\n" + t.Faint.Render("enter jump · esc close"))
	return lipgloss.Place(r.w, r.vp.Height(), lipgloss.Center, lipgloss.Center, box)
}

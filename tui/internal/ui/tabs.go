package ui

import (
	"strings"

	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"
)

// renderTabs draws an inline tab strip like:  professional  [personal]  hobbies
func renderTabs(env *Env, labels []string, active, width int) string {
	t := env.Theme
	parts := make([]string, len(labels))
	for i, l := range labels {
		if i == active {
			parts[i] = t.Selected.Render("[" + l + "]")
		} else {
			parts[i] = t.Faint.Render(" " + l + " ")
		}
	}
	line := strings.Join(parts, " ")
	if lipgloss.Width(line) > width && active < len(labels) {
		// Fall back to a compact "‹ label › 2/6" form.
		line = t.Faint.Render("‹ ") + t.Selected.Render(labels[active]) + t.Faint.Render(" ›")
	}
	return xansi.Truncate(line, width, "…")
}

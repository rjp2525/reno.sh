package ui

import (
	"strings"

	"charm.land/bubbles/v2/spinner"
	tea "charm.land/bubbletea/v2"
	"charm.land/lipgloss/v2"
)

// loader tracks a loading/error state and owns a spinner.
type loader struct {
	env     *Env
	spin    spinner.Model
	loading bool
	ticking bool
	err     error
}

func newLoader(env *Env) loader {
	return loader{env: env, spin: spinner.New(spinner.WithSpinner(spinner.MiniDot))}
}

// start marks loading and makes sure exactly one tick chain is running.
func (l *loader) start() tea.Cmd {
	l.loading, l.err = true, nil
	if l.ticking {
		return nil
	}
	l.ticking = true
	return l.spin.Tick
}

func (l *loader) done(err error) { l.loading, l.err = false, err }

func (l *loader) update(msg tea.Msg) tea.Cmd {
	tick, ok := msg.(spinner.TickMsg)
	if !ok || tick.ID != l.spin.ID() {
		return nil
	}
	if !l.loading {
		l.ticking = false
		return nil
	}
	var cmd tea.Cmd
	l.spin, cmd = l.spin.Update(msg)
	return cmd
}

// view renders the loading or error state centred in w x h, or "" when idle.
func (l *loader) view(what string, w, h int) string {
	t := l.env.Theme
	var body string
	switch {
	case l.loading:
		l.spin.Style = lipgloss.NewStyle().Foreground(t.Primary)
		body = l.spin.View() + " " + t.Faint.Render("loading "+what+"…")
	case l.err != nil:
		body = strings.Join([]string{
			t.Error.Render("✗ " + friendlyError(l.err)),
			"",
			t.Faint.Render("// press ") + t.Key.Render("r") + t.Faint.Render(" to retry, ") +
				t.Key.Render("esc") + t.Faint.Render(" to go back"),
		}, "\n")
	default:
		return ""
	}
	return lipgloss.Place(max(w, 1), max(h, 1), lipgloss.Center, lipgloss.Center, body)
}

func (l *loader) busy() bool { return l.loading || l.err != nil }

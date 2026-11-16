// Package ui implements the reno.sh terminal UI as a Bubble Tea program. The
// same model is served over SSH (one per session) and in --local mode.
package ui

import (
	"context"
	"errors"
	"fmt"
	"net"
	"time"

	"charm.land/bubbles/v2/key"
	tea "charm.land/bubbletea/v2"
	"github.com/charmbracelet/colorprofile"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/ui/theme"
)

// Options configure a new UI model.
type Options struct {
	Client      *api.Client
	SiteURL     string
	SSHHost     string
	SSHPort     int
	IdleTimeout time.Duration // 0 disables the in-app idle timeout
	Context     context.Context
}

// Env is the per-session state shared by every screen.
type Env struct {
	Client  *api.Client
	Theme   *theme.Theme
	Profile colorprofile.Profile
	SiteURL string
	SSHHost string
	SSHPort int
	Site    api.Site
	Ctx     context.Context
}

// Screen is one top-level section of the app. Screens are pointers and are
// updated in place.
type Screen interface {
	// Activate is called whenever the section becomes visible; it is where
	// lazy data loading starts.
	Activate() tea.Cmd
	Update(msg tea.Msg) tea.Cmd
	View() string
	SetSize(width, height int)
	// Back handles q/esc inside the screen (detail → list, clear filter).
	// It returns false when the screen is at its root.
	Back() bool
	// Capturing reports that the screen wants every key (text entry).
	Capturing() bool
	// Crumb is extra breadcrumb text for the status bar.
	Crumb() string
	// Keys lists contextual bindings for the help overlay and hint bar.
	Keys() []key.Binding
}

// Messages shared between screens and the root model.
type (
	// goSectionMsg switches to a section.
	goSectionMsg struct{ section section }
	// themeChangedMsg is broadcast when the theme or colour profile changes.
	themeChangedMsg struct{}
	// siteMsg carries the /site payload.
	siteMsg struct {
		site api.Site
		err  error
	}
)

func goSection(s section) tea.Cmd { return func() tea.Msg { return goSectionMsg{s} } }

// friendlyError turns an API error into something a visitor can act on.
func friendlyError(err error) string {
	if err == nil {
		return ""
	}
	var se *api.StatusError
	var ne net.Error
	var oe *net.OpError
	switch {
	case errors.Is(err, api.ErrNotFound):
		return "404: that page wandered off."
	case errors.Is(err, api.ErrThrottled):
		return "whoa, slow down. too many requests, try again in a minute."
	case errors.Is(err, context.DeadlineExceeded), errors.As(err, &ne) && ne.Timeout():
		return "reno.sh took too long to answer. it might be napping."
	case errors.As(err, &se) && se.Status >= 500:
		return fmt.Sprintf("the reno.sh API is having a moment (%d). try again shortly.", se.Status)
	case errors.As(err, &se):
		return fmt.Sprintf("unexpected response from the API (%d).", se.Status)
	case errors.As(err, &oe):
		return "can't reach reno.sh right now. the site may be down."
	}
	return "something went wrong talking to the API."
}

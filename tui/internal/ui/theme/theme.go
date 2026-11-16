// Package theme holds the reno.sh colour palette and shared styles. Colours
// adapt to light/dark terminal backgrounds; Bubble Tea downsamples them to
// 256/16 colours automatically on terminals that lack truecolor.
package theme

import (
	"image/color"

	"charm.land/lipgloss/v2"
)

// Brand colours from resources/css/app.css and the Vue pages.
const (
	HexPrimary    = "#43D9AD" // github-text / accent green
	HexOrange     = "#FEA55F"
	HexBlue       = "#4D5BCE" // --color-purple on the site
	HexMenu       = "#607B96" // --color-menu
	HexNavy       = "#1E2D3D" // --color-navy (borders)
	HexBackground = "#011627" // --color-background
	HexRed        = "#E99287" // string literal colour on the homepage
	HexText       = "#E5E9F0"
)

// Theme is a resolved palette plus styles for one background brightness.
type Theme struct {
	IsDark bool

	Primary, Orange, Blue, Muted, Border, Text, Bright, Red, Subtle color.Color

	Title      lipgloss.Style // section headings
	Heading    lipgloss.Style
	Comment    lipgloss.Style // "// like this"
	Keyword    lipgloss.Style
	Ident      lipgloss.Style
	String     lipgloss.Style
	Faint      lipgloss.Style
	Body       lipgloss.Style
	Accent     lipgloss.Style
	Warn       lipgloss.Style
	Error      lipgloss.Style
	Success    lipgloss.Style
	Link       lipgloss.Style
	Selected   lipgloss.Style
	Box        lipgloss.Style
	Tag        lipgloss.Style
	TabActive  lipgloss.Style
	TabIdle    lipgloss.Style
	StatusBar  lipgloss.Style
	StatusMode lipgloss.Style
	StatusInfo lipgloss.Style
	Key        lipgloss.Style
	KeyDesc    lipgloss.Style
}

// New builds the theme for a light or dark background.
func New(isDark bool) *Theme {
	ld := lipgloss.LightDark(isDark)
	c := func(light, dark string) color.Color { return ld(lipgloss.Color(light), lipgloss.Color(dark)) }

	t := &Theme{
		IsDark:  isDark,
		Primary: c("#16946F", HexPrimary),
		Orange:  c("#C2620F", HexOrange),
		Blue:    c("#3A47B5", "#7C88F0"),
		Muted:   c("#5A6B7D", HexMenu),
		Border:  c("#C3CED9", HexNavy),
		Text:    c("#1E2D3D", "#C5D0DC"),
		Bright:  c("#0B1621", HexText),
		Red:     c("#B4432F", HexRed),
		Subtle:  c("#E4EAF0", "#0D2133"),
	}
	base := lipgloss.NewStyle()
	t.Title = base.Foreground(t.Bright).Bold(true)
	t.Heading = base.Foreground(t.Primary).Bold(true)
	t.Comment = base.Foreground(t.Muted)
	t.Keyword = base.Foreground(t.Blue)
	t.Ident = base.Foreground(t.Primary)
	t.String = base.Foreground(t.Red)
	t.Faint = base.Foreground(t.Muted)
	t.Body = base.Foreground(t.Text)
	t.Accent = base.Foreground(t.Orange)
	t.Warn = base.Foreground(t.Orange)
	t.Error = base.Foreground(t.Red).Bold(true)
	t.Success = base.Foreground(t.Primary).Bold(true)
	t.Link = base.Foreground(t.Blue).Underline(true)
	t.Selected = base.Foreground(t.Orange).Bold(true)
	t.Box = base.Border(lipgloss.RoundedBorder()).BorderForeground(t.Border).Padding(0, 1)
	t.Tag = base.Foreground(t.Primary).Background(t.Subtle).Padding(0, 1)
	t.TabActive = base.Foreground(t.Bright).Bold(true).
		Border(lipgloss.NormalBorder(), false, false, true, false).BorderForeground(t.Orange)
	t.TabIdle = base.Foreground(t.Muted).
		Border(lipgloss.HiddenBorder(), false, false, true, false)
	t.StatusBar = base.Foreground(t.Muted).Background(t.Subtle)
	t.StatusMode = base.Foreground(lipgloss.Color(HexBackground)).Background(t.Primary).Bold(true).Padding(0, 1)
	t.StatusInfo = base.Foreground(t.Bright).Background(t.Border).Padding(0, 1)
	t.Key = base.Foreground(t.Orange).Bold(true)
	t.KeyDesc = base.Foreground(t.Muted)
	return t
}

// Hex parses a CSS hex colour, returning fallback when it is empty/invalid.
func Hex(s string, fallback color.Color) color.Color {
	if len(s) != 7 && len(s) != 4 || s[0] != '#' {
		return fallback
	}
	return lipgloss.Color(s)
}

package components

import (
	"regexp"
	"strings"

	"charm.land/glamour/v2"
	"charm.land/glamour/v2/ansi"
	"charm.land/glamour/v2/styles"
	xansi "github.com/charmbracelet/x/ansi"
)

func sp(s string) *string { return &s }
func bp(b bool) *bool     { return &b }
func up(u uint) *uint     { return &u }

// MarkdownStyle returns a glamour style tuned to the reno.sh palette.
func MarkdownStyle(isDark bool) ansi.StyleConfig {
	if !isDark {
		s := styles.LightStyleConfig
		s.Document.Margin = up(0)
		s.H1.StylePrimitive = ansi.StylePrimitive{Prefix: "# ", Color: sp("#16946F"), Bold: bp(true)}
		s.H2.StylePrimitive.Color = sp("#16946F")
		s.H3.StylePrimitive.Color = sp("#C2620F")
		s.Link.Color = sp("#3A47B5")
		s.LinkText.Color = sp("#16946F")
		s.Code.StylePrimitive.Color = sp("#C2620F")
		return s
	}
	s := styles.DarkStyleConfig
	s.Document.Margin = up(0)
	s.Document.StylePrimitive.Color = sp("#C5D0DC")
	s.Heading.StylePrimitive.Color = sp("#43D9AD")
	s.H1.StylePrimitive = ansi.StylePrimitive{Prefix: "# ", Color: sp("#43D9AD"), Bold: bp(true)}
	s.H2.StylePrimitive.Color = sp("#43D9AD")
	s.H3.StylePrimitive.Color = sp("#FEA55F")
	s.H4.StylePrimitive.Color = sp("#FEA55F")
	s.Link.Color = sp("#7C88F0")
	s.LinkText.Color = sp("#43D9AD")
	s.Code.StylePrimitive.Color = sp("#FEA55F")
	s.Code.StylePrimitive.BackgroundColor = sp("#0D2133")
	s.HorizontalRule.Color = sp("#1E2D3D")
	s.BlockQuote.StylePrimitive.Color = sp("#607B96")
	s.Item.BlockPrefix = "› "
	return s
}

// RenderMarkdown renders markdown at the given wrap width. On failure the
// source is returned word-wrapped so content is never lost.
func RenderMarkdown(md string, width int, isDark bool) string {
	width = max(width, 20)
	r, err := glamour.NewTermRenderer(
		glamour.WithStyles(MarkdownStyle(isDark)),
		glamour.WithWordWrap(width),
		glamour.WithTableWrap(true),
	)
	if err == nil {
		out, rerr := r.Render(md)
		if rerr == nil {
			return strings.Trim(out, "\n")
		}
	}
	return xansi.Wordwrap(md, width, "")
}

// Heading is a table-of-contents entry.
type Heading struct {
	Level int
	Title string
	Line  int // line in the rendered output, -1 when not located
}

var (
	atxHeading = regexp.MustCompile(`^(#{1,6})\s+(.+?)\s*#*\s*$`)
	mdInline   = regexp.MustCompile("[*_`~]|\\[([^\\]]*)\\]\\([^)]*\\)")
)

// TOC extracts ATX headings (outside fenced code) from markdown source.
func TOC(md string) []Heading {
	var out []Heading
	fence := ""
	for _, line := range strings.Split(md, "\n") {
		trim := strings.TrimSpace(line)
		if strings.HasPrefix(trim, "```") || strings.HasPrefix(trim, "~~~") {
			mark := trim[:3]
			switch {
			case fence == "":
				fence = mark
			case fence == mark:
				fence = ""
			}
			continue
		}
		if fence != "" {
			continue
		}
		if m := atxHeading.FindStringSubmatch(trim); m != nil {
			title := mdInline.ReplaceAllString(m[2], "$1")
			out = append(out, Heading{Level: len(m[1]), Title: strings.TrimSpace(title), Line: -1})
		}
	}
	return out
}

// LocateHeadings finds each heading's line in rendered (ANSI) output by
// scanning forward for the heading text, in document order, starting at
// line start. Lines styled as headings ("# …") are preferred; if inline
// markup changed the text (links render with their URL), progressively
// shorter word prefixes are tried.
func LocateHeadings(rendered string, heads []Heading, start int) []Heading {
	lines := strings.Split(xansi.Strip(rendered), "\n")
	for i := range lines {
		lines[i] = strings.ToLower(strings.TrimSpace(lines[i]))
	}
	out := make([]Heading, len(heads))
	copy(out, heads)
	pos := max(start, 0)
	find := func(needle string, headingOnly bool) int {
		for j := pos; j < len(lines); j++ {
			if headingOnly && !strings.HasPrefix(lines[j], "#") {
				continue
			}
			if strings.Contains(lines[j], needle) {
				return j
			}
		}
		return -1
	}
	for i := range out {
		words := strings.Fields(strings.ToLower(out[i].Title))
		line := -1
		for n := len(words); n > 0 && line < 0; n-- {
			needle := strings.Join(words[:n], " ")
			if line = find(needle, true); line < 0 && n == len(words) {
				line = find(needle, false)
			}
		}
		if line >= 0 {
			out[i].Line = line
			pos = line + 1
		}
	}
	return out
}

// Package components holds reusable rendering helpers for the TUI: the
// figlet-style banner, half-block images, markdown, bars and formatting.
package components

import (
	"image/color"
	"strings"
	"unicode"

	"charm.land/lipgloss/v2"
)

const glyphRows = 5

// font is a hand-drawn 5-row block font. '#' is a filled cell.
var font = map[rune][glyphRows]string{
	'A':  {" ### ", "#   #", "#####", "#   #", "#   #"},
	'B':  {"#### ", "#   #", "#### ", "#   #", "#### "},
	'C':  {" ####", "#    ", "#    ", "#    ", " ####"},
	'D':  {"#### ", "#   #", "#   #", "#   #", "#### "},
	'E':  {"#####", "#    ", "#### ", "#    ", "#####"},
	'F':  {"#####", "#    ", "#### ", "#    ", "#    "},
	'G':  {" ####", "#    ", "#  ##", "#   #", " ####"},
	'H':  {"#   #", "#   #", "#####", "#   #", "#   #"},
	'I':  {"###", " # ", " # ", " # ", "###"},
	'J':  {"  ###", "   # ", "   # ", "#  # ", " ##  "},
	'K':  {"#   #", "#  # ", "###  ", "#  # ", "#   #"},
	'L':  {"#    ", "#    ", "#    ", "#    ", "#####"},
	'M':  {"#   #", "## ##", "# # #", "#   #", "#   #"},
	'N':  {"#   #", "##  #", "# # #", "#  ##", "#   #"},
	'O':  {" ### ", "#   #", "#   #", "#   #", " ### "},
	'P':  {"#### ", "#   #", "#### ", "#    ", "#    "},
	'Q':  {" ### ", "#   #", "# # #", "#  # ", " ## #"},
	'R':  {"#### ", "#   #", "#### ", "#  # ", "#   #"},
	'S':  {" ####", "#    ", " ### ", "    #", "#### "},
	'T':  {"#####", "  #  ", "  #  ", "  #  ", "  #  "},
	'U':  {"#   #", "#   #", "#   #", "#   #", " ### "},
	'V':  {"#   #", "#   #", "#   #", " # # ", "  #  "},
	'W':  {"#   #", "#   #", "# # #", "## ##", "#   #"},
	'X':  {"#   #", " # # ", "  #  ", " # # ", "#   #"},
	'Y':  {"#   #", " # # ", "  #  ", "  #  ", "  #  "},
	'Z':  {"#####", "   # ", "  #  ", " #   ", "#####"},
	'.':  {" ", " ", " ", " ", "#"},
	'-':  {"    ", "    ", "####", "    ", "    "},
	'\'': {"#", "#", " ", " ", " "},
	' ':  {"  ", "  ", "  ", "  ", "  "},
}

// BannerLines renders text in the block font, returning the raw rows (with
// '█' cells) or ok=false when a character is not in the font.
func BannerLines(text string) (rows []string, ok bool) {
	var b [glyphRows]strings.Builder
	first := true
	for _, r := range strings.ToUpper(text) {
		g, found := font[unicode.ToUpper(r)]
		if !found {
			return nil, false
		}
		for i := range glyphRows {
			if !first {
				b[i].WriteByte(' ')
			}
			b[i].WriteString(strings.ReplaceAll(g[i], "#", "█"))
		}
		first = false
	}
	rows = make([]string, glyphRows)
	for i := range glyphRows {
		rows[i] = strings.TrimRight(b[i].String(), " ")
	}
	return rows, true
}

// Banner renders the largest variant of the candidates that fits in width,
// painted with a horizontal gradient. If none fit it falls back to a bold
// plain-text line of the first candidate.
func Banner(width int, from, to color.Color, candidates ...string) string {
	for _, c := range candidates {
		rows, ok := BannerLines(c)
		if !ok {
			continue
		}
		w := 0
		for _, r := range rows {
			w = max(w, lipgloss.Width(r))
		}
		if w > width {
			continue
		}
		return paint(rows, w, from, to)
	}
	if len(candidates) == 0 {
		return ""
	}
	return lipgloss.NewStyle().Bold(true).Foreground(from).Render(candidates[0])
}

func paint(rows []string, w int, from, to color.Color) string {
	ramp := lipgloss.Blend1D(max(w, 2), from, to)
	out := make([]string, len(rows))
	for i, row := range rows {
		var sb strings.Builder
		col := 0
		for _, r := range row {
			if r == ' ' {
				sb.WriteRune(' ')
			} else {
				sb.WriteString(lipgloss.NewStyle().Foreground(ramp[min(col, len(ramp)-1)]).Render(string(r)))
			}
			col++
		}
		out[i] = sb.String()
	}
	return strings.Join(out, "\n")
}

package components

import (
	"strings"
	"testing"

	"charm.land/lipgloss/v2"
	"github.com/charmbracelet/colorprofile"
	xansi "github.com/charmbracelet/x/ansi"

	"github.com/rjp2525/reno.sh/tui/internal/api/apitest"
)

func TestBannerAdaptsToWidth(t *testing.T) {
	from, to := lipgloss.Color("#43D9AD"), lipgloss.Color("#4D5BCE")
	for _, w := range []int{120, 80, 60, 40, 10} {
		out := Banner(w, from, to, "Reno Philibert", "Reno", "reno.sh")
		for _, line := range strings.Split(out, "\n") {
			if lw := xansi.StringWidth(line); lw > w && w >= 20 {
				t.Fatalf("width %d: banner line is %d wide", w, lw)
			}
		}
	}
	if out := xansi.Strip(Banner(10, from, to, "Reno Philibert")); out != "Reno Philibert" {
		t.Fatalf("narrow fallback should be plain text, got %q", out)
	}
	if _, ok := BannerLines("ünïcode"); ok {
		t.Fatal("unsupported characters must be reported")
	}
	rows, ok := BannerLines("reno.sh")
	if !ok || len(rows) != 5 {
		t.Fatalf("BannerLines: %v %v", rows, ok)
	}
}

func TestFitCells(t *testing.T) {
	cases := []struct{ w, h, mc, mr, wc, wr int }{
		{100, 50, 80, 100, 80, 20}, // wide image: width bound
		{50, 100, 80, 20, 20, 20},  // tall image: height bound
		{10, 10, 0, 10, 0, 0},      // no room
	}
	for _, c := range cases {
		cols, rows := FitCells(c.w, c.h, c.mc, c.mr)
		if cols != c.wc || rows != c.wr {
			t.Errorf("FitCells(%d,%d,%d,%d) = %d,%d want %d,%d", c.w, c.h, c.mc, c.mr, cols, rows, c.wc, c.wr)
		}
	}
}

func TestHalfBlock(t *testing.T) {
	img := apitest.TestImage(64, 32)
	out := HalfBlock(img, 32, 40, colorprofile.TrueColor)
	lines := strings.Split(out, "\n")
	if len(lines) != 8 {
		t.Fatalf("want 8 rows (32 cols × 16px / 2), got %d", len(lines))
	}
	if w := xansi.StringWidth(lines[0]); w != 32 {
		t.Fatalf("row width %d, want 32", w)
	}
	if !strings.Contains(out, "38;2;") || !strings.Contains(out, "48;2;") {
		t.Fatal("truecolor output should use 24-bit SGR")
	}
	if out256 := HalfBlock(img, 32, 40, colorprofile.ANSI256); strings.Contains(out256, "38;2;") || !strings.Contains(out256, "38;5;") {
		t.Fatal("256-colour output should use indexed SGR")
	}
	if HalfBlock(img, 32, 40, colorprofile.ASCII) != "" {
		t.Fatal("no-colour terminals get no image")
	}
	if HalfBlock(nil, 32, 40, colorprofile.TrueColor) != "" {
		t.Fatal("nil image")
	}
}

func TestMoney(t *testing.T) {
	cases := map[string]string{
		Money(6500, "USD"):   "$65.00",
		Money(123456, "usd"): "$1,234.56",
		Money(2500, "EUR"):   "€25.00",
		Money(1500, "JPY"):   "¥1,500",
		Money(999, "CHF"):    "9.99 CHF",
		Money(5, ""):         "$0.05",
	}
	for got, want := range cases {
		if got != want {
			t.Errorf("got %q want %q", got, want)
		}
	}
}

func TestTOCAndLocate(t *testing.T) {
	md := "# Title\n\nintro\n\n```sh\n# not a heading\n```\n\n## First [link](http://x) **bold**\n\ntext\n\n### Sub\n"
	toc := TOC(md)
	if len(toc) != 3 || toc[1].Title != "First link bold" || toc[2].Level != 3 {
		t.Fatalf("toc: %+v", toc)
	}
	rendered := RenderMarkdown(md, 60, true)
	located := LocateHeadings(rendered, toc, 0)
	prev := -1
	for _, h := range located {
		if h.Line <= prev {
			t.Fatalf("headings should be located in order: %+v", located)
		}
		prev = h.Line
	}
}

func TestRangeAndDates(t *testing.T) {
	if got := Range("2022-01-01", "", MonthYear); got != "Jan 2022 → present" {
		t.Fatalf("range: %q", got)
	}
	if got := Range("2015-06-01", "2021-12-31", Year); got != "2015 → 2021" {
		t.Fatalf("range: %q", got)
	}
	if got := xansi.Strip(Bar(3, 4, 8, lipgloss.Color("#fff"), lipgloss.Color("#000"))); got != "██████░░" {
		t.Fatalf("bar: %q", got)
	}
}

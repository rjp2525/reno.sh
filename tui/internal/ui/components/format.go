package components

import (
	"fmt"
	"image/color"
	"strconv"
	"strings"
	"time"

	"charm.land/lipgloss/v2"
	xansi "github.com/charmbracelet/x/ansi"
	"github.com/rjp2525/reno.sh/tui/internal/api"
)

// zeroDecimal lists ISO 4217 currencies without minor units.
var zeroDecimal = map[string]bool{"JPY": true, "KRW": true, "VND": true, "CLP": true, "ISK": true}

var symbols = map[string]string{"USD": "$", "CAD": "CA$", "AUD": "A$", "NZD": "NZ$", "EUR": "€", "GBP": "£", "JPY": "¥"}

// Money formats integer minor units with a currency code.
func Money(minor int64, currency string) string {
	cur := strings.ToUpper(strings.TrimSpace(currency))
	if cur == "" {
		cur = "USD"
	}
	var amount string
	if zeroDecimal[cur] {
		amount = groupThousands(strconv.FormatInt(minor, 10))
	} else {
		neg := minor < 0
		if neg {
			minor = -minor
		}
		amount = fmt.Sprintf("%s.%02d", groupThousands(strconv.FormatInt(minor/100, 10)), minor%100)
		if neg {
			amount = "-" + amount
		}
	}
	if sym, ok := symbols[cur]; ok {
		return sym + amount
	}
	return amount + " " + cur
}

func groupThousands(s string) string {
	if len(s) <= 3 {
		return s
	}
	var b strings.Builder
	pre := len(s) % 3
	if pre > 0 {
		b.WriteString(s[:pre])
	}
	for i := pre; i < len(s); i += 3 {
		if b.Len() > 0 {
			b.WriteByte(',')
		}
		b.WriteString(s[i : i+3])
	}
	return b.String()
}

// Date formats a time as "Jan 2, 2006", or "" for zero.
func Date(t time.Time) string {
	if t.IsZero() {
		return ""
	}
	return t.Format("Jan 2, 2006")
}

// MonthYear formats an API date string as "Jan 2006".
func MonthYear(s string) string {
	t := api.ParseTime(s)
	if t.IsZero() {
		return strings.TrimSpace(s)
	}
	return t.Format("Jan 2006")
}

// Year formats an API date string as "2006".
func Year(s string) string {
	t := api.ParseTime(s)
	if t.IsZero() {
		return strings.TrimSpace(s)
	}
	return t.Format("2006")
}

// Range formats a start/end pair; empty end renders as "present".
func Range(start, end string, fmtFn func(string) string) string {
	a := fmtFn(start)
	b := "present"
	if strings.TrimSpace(end) != "" {
		b = fmtFn(end)
	}
	if a == "" {
		return b
	}
	return a + " → " + b
}

// Bar renders a filled/empty meter of the given cell width.
func Bar(value, maxValue, width int, fill, empty color.Color) string {
	if maxValue <= 0 || width <= 0 {
		return ""
	}
	value = min(max(value, 0), maxValue)
	n := value * width / maxValue
	return lipgloss.NewStyle().Foreground(fill).Render(strings.Repeat("█", n)) +
		lipgloss.NewStyle().Foreground(empty).Render(strings.Repeat("░", width-n))
}

// Progress renders a reading progress line like "━━━━──── 42%".
func Progress(pct float64, width int, fill, empty color.Color) string {
	pct = min(max(pct, 0), 1)
	label := fmt.Sprintf(" %3.0f%%", pct*100)
	w := max(width-len(label), 4)
	n := int(pct * float64(w))
	return lipgloss.NewStyle().Foreground(fill).Render(strings.Repeat("━", n)) +
		lipgloss.NewStyle().Foreground(empty).Render(strings.Repeat("─", w-n)) +
		lipgloss.NewStyle().Foreground(fill).Render(label)
}

// Truncate shortens s (ANSI-aware) to width with an ellipsis.
func Truncate(s string, width int) string {
	if width <= 0 {
		return ""
	}
	return xansi.Truncate(s, width, "…")
}

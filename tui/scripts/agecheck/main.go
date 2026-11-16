// Command agecheck reads the concatenated JSON stream produced by
// `go list -m -json all` on stdin and exits non-zero if any module version
// was published more recently than the allowed minimum age.
package main

import (
	"encoding/json"
	"errors"
	"flag"
	"fmt"
	"io"
	"os"
	"sort"
	"time"
)

type module struct {
	Path    string
	Version string
	Time    *time.Time
	Main    bool
	Replace *module
}

func main() {
	days := flag.Int("days", 7, "minimum age in days for every module version")
	verbose := flag.Bool("v", false, "print every module with its publish time")
	flag.Parse()

	cutoff := time.Now().UTC().Add(-time.Duration(*days) * 24 * time.Hour)
	dec := json.NewDecoder(os.Stdin)

	var mods []module
	for {
		var m module
		if err := dec.Decode(&m); err != nil {
			if errors.Is(err, io.EOF) {
				break
			}
			fmt.Fprintln(os.Stderr, "agecheck: decode:", err)
			os.Exit(2)
		}
		if m.Replace != nil {
			m = *m.Replace
		}
		mods = append(mods, m)
	}
	sort.Slice(mods, func(i, j int) bool { return mods[i].Path < mods[j].Path })

	var bad, checked int
	for _, m := range mods {
		if m.Main || m.Time == nil {
			continue
		}
		checked++
		age := time.Since(*m.Time).Hours() / 24
		if *verbose {
			fmt.Printf("%-60s %-40s %s (%.1fd)\n", m.Path, m.Version, m.Time.UTC().Format(time.RFC3339), age)
		}
		if m.Time.After(cutoff) {
			bad++
			fmt.Fprintf(os.Stderr, "TOO NEW: %s@%s published %s (%.1f days ago, minimum %d)\n",
				m.Path, m.Version, m.Time.UTC().Format(time.RFC3339), age, *days)
		}
	}
	if bad > 0 {
		fmt.Fprintf(os.Stderr, "agecheck: %d of %d modules are newer than %d days\n", bad, checked, *days)
		os.Exit(1)
	}
	fmt.Printf("agecheck: all %d modules are at least %d days old\n", checked, *days)
}

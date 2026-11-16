// Command reno-tui serves the reno.sh portfolio as a terminal UI over SSH
// (ssh reno.sh -p 2222), or runs it in the current terminal with --local.
package main

import (
	"context"
	"flag"
	"fmt"
	"os"
	"os/signal"
	"syscall"

	tea "charm.land/bubbletea/v2"
	"charm.land/log/v2"

	"github.com/rjp2525/reno.sh/tui/internal/api"
	"github.com/rjp2525/reno.sh/tui/internal/config"
	"github.com/rjp2525/reno.sh/tui/internal/server"
	"github.com/rjp2525/reno.sh/tui/internal/ui"
)

// version is set at build time with -ldflags "-X main.version=…".
var version = "dev"

func main() {
	local := flag.Bool("local", false, "run the TUI in this terminal instead of serving SSH")
	showVersion := flag.Bool("version", false, "print version and exit")
	flag.Parse()

	if *showVersion {
		fmt.Println("reno-tui", version)
		return
	}

	cfg, err := config.Load()
	if err != nil {
		fmt.Fprintln(os.Stderr, "config:", err)
		os.Exit(2)
	}

	client := api.New(api.Options{
		BaseURL:   cfg.APIBase,
		TTL:       cfg.CacheTTL,
		Timeout:   cfg.HTTPTimeout,
		UserAgent: "reno.sh-tui/" + version,
	})

	ctx, stop := signal.NotifyContext(context.Background(), os.Interrupt, syscall.SIGTERM)
	defer stop()

	if *local {
		os.Exit(runLocal(ctx, cfg, client))
	}

	logger := log.NewWithOptions(os.Stderr, log.Options{ReportTimestamp: true, Prefix: "reno-tui"})
	srv, err := server.New(cfg, client, logger)
	if err != nil {
		logger.Fatal("could not create server", "err", err)
	}
	if err := srv.ListenAndServe(ctx); err != nil {
		logger.Fatal("server error", "err", err)
	}
	logger.Info("bye")
}

func runLocal(ctx context.Context, cfg config.Config, client *api.Client) int {
	m := ui.New(ui.Options{
		Client:  client,
		SiteURL: cfg.SiteURL,
		SSHHost: cfg.SSHHost,
		SSHPort: cfg.SSHPort,
		Context: ctx,
	})
	if _, err := tea.NewProgram(m, tea.WithContext(ctx)).Run(); err != nil && ctx.Err() == nil {
		fmt.Fprintln(os.Stderr, "error:", err)
		return 1
	}
	return 0
}

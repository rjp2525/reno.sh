// Package config loads runtime configuration from the environment.
package config

import (
	"fmt"
	"os"
	"strconv"
	"strings"
	"time"
)

// Config holds every tunable for the TUI and the SSH server.
type Config struct {
	// APIBase is the fully qualified API root, e.g. https://reno.sh/api/v1.
	APIBase string
	// SiteURL is the public web URL used for links shown to visitors.
	SiteURL string
	// SSHHost is the hostname advertised in the status bar.
	SSHHost string
	// SSHPort is the port the SSH server listens on.
	SSHPort int
	// ListenHost is the interface to bind (default all).
	ListenHost string
	// HostKeyPath is where the persistent ed25519 host key lives.
	HostKeyPath string

	CacheTTL      time.Duration
	HTTPTimeout   time.Duration
	IdleTimeout   time.Duration
	MaxSession    time.Duration
	MaxSessions   int
	ShutdownGrace time.Duration
}

// Load reads configuration from the environment, applying defaults.
func Load() (Config, error) {
	return FromLookup(os.LookupEnv)
}

// FromLookup builds a Config using the given lookup function (os.LookupEnv
// in production, a map in tests).
func FromLookup(lookup func(string) (string, bool)) (Config, error) {
	get := func(key, def string) string {
		if v, ok := lookup(key); ok && strings.TrimSpace(v) != "" {
			return strings.TrimSpace(v)
		}
		return def
	}

	c := Config{
		APIBase:     APIBase(get("RENO_API_URL", "https://reno.sh")),
		SiteURL:     strings.TrimRight(get("RENO_SITE_URL", "https://reno.sh"), "/"),
		SSHHost:     get("RENO_SSH_HOST", "reno.sh"),
		ListenHost:  get("RENO_LISTEN_HOST", "0.0.0.0"),
		HostKeyPath: get("RENO_HOST_KEY_PATH", ".ssh/host_ed25519"),
	}

	var err error
	if c.SSHPort, err = intVar(get, "RENO_SSH_PORT", 2222); err != nil {
		return c, err
	}
	if c.SSHPort < 1 || c.SSHPort > 65535 {
		return c, fmt.Errorf("RENO_SSH_PORT out of range: %d", c.SSHPort)
	}
	if c.MaxSessions, err = intVar(get, "RENO_MAX_SESSIONS", 200); err != nil {
		return c, err
	}
	durations := []struct {
		dst *time.Duration
		key string
		def time.Duration
	}{
		{&c.CacheTTL, "RENO_CACHE_TTL", 60 * time.Second},
		{&c.HTTPTimeout, "RENO_HTTP_TIMEOUT", 10 * time.Second},
		{&c.IdleTimeout, "RENO_IDLE_TIMEOUT", 10 * time.Minute},
		{&c.MaxSession, "RENO_MAX_SESSION", 2 * time.Hour},
		{&c.ShutdownGrace, "RENO_SHUTDOWN_GRACE", 15 * time.Second},
	}
	for _, d := range durations {
		v := get(d.key, "")
		if v == "" {
			*d.dst = d.def
			continue
		}
		parsed, perr := time.ParseDuration(v)
		if perr != nil {
			return c, fmt.Errorf("%s: %w", d.key, perr)
		}
		*d.dst = parsed
	}
	return c, nil
}

// APIBase normalises a site URL into the versioned API root. Values that
// already end in /api/v1 are left alone.
func APIBase(raw string) string {
	base := strings.TrimRight(raw, "/")
	if strings.HasSuffix(base, "/api/v1") {
		return base
	}
	return base + "/api/v1"
}

func intVar(get func(string, string) string, key string, def int) (int, error) {
	v := get(key, "")
	if v == "" {
		return def, nil
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		return 0, fmt.Errorf("%s: %w", key, err)
	}
	return n, nil
}

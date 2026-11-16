// Package config loads runtime configuration from the environment.
package config

import (
	"bufio"
	"fmt"
	"os"
	"path/filepath"
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

// Load reads configuration from the process environment, falling back to the
// Laravel app's .env (RENO_ENV_FILE, default ./.env). Run from the site root
// (Forge: <site>/current) and the daemon needs no variables of its own.
func Load() (Config, error) {
	path := ".env"
	if v, ok := os.LookupEnv("RENO_ENV_FILE"); ok && v != "" {
		path = v
	}
	file, err := ReadEnvFile(path)
	if err != nil && !os.IsNotExist(err) {
		return Config{}, fmt.Errorf("%s: %w", path, err)
	}
	return FromLookup(func(key string) (string, bool) {
		if v, ok := os.LookupEnv(key); ok {
			return v, true
		}
		v, ok := file[key]
		return v, ok
	})
}

// ReadEnvFile parses a dotenv file: KEY=VALUE lines, optional "export ",
// single/double quotes, # comments. ${VAR} references are not expanded.
func ReadEnvFile(path string) (map[string]string, error) {
	f, err := os.Open(path)
	if err != nil {
		return nil, err
	}
	defer f.Close()

	vars := map[string]string{}
	sc := bufio.NewScanner(f)
	for sc.Scan() {
		line := strings.TrimSpace(sc.Text())
		if line == "" || strings.HasPrefix(line, "#") {
			continue
		}
		key, val, ok := strings.Cut(strings.TrimPrefix(line, "export "), "=")
		if !ok {
			continue
		}
		val = strings.TrimSpace(val)
		if n := len(val); n >= 2 && (val[0] == '"' || val[0] == '\'') && val[n-1] == val[0] {
			val = val[1 : n-1]
		} else if i := strings.Index(val, " #"); i >= 0 {
			val = strings.TrimSpace(val[:i])
		}
		vars[strings.TrimSpace(key)] = val
	}
	return vars, sc.Err()
}

// defaultHostKeyPath keeps the host key outside the release directory so it
// survives deploys (a new key would make returning visitors' ssh refuse to connect).
func defaultHostKeyPath() string {
	if home, err := os.UserHomeDir(); err == nil && home != "" {
		return filepath.Join(home, ".reno-tui", "host_ed25519")
	}
	return ".ssh/host_ed25519"
}

// FromLookup builds a Config using the given lookup function (os.LookupEnv
// in production, a map in tests).
func FromLookup(lookup func(string) (string, bool)) (Config, error) {
	// get returns the first non-empty key (RENO_* first, then the Laravel names), else def.
	get := func(def string, keys ...string) string {
		for _, key := range keys {
			if v, ok := lookup(key); ok && strings.TrimSpace(v) != "" {
				return strings.TrimSpace(v)
			}
		}
		return def
	}

	siteURL := get("https://reno.sh", "RENO_SITE_URL", "APP_URL")
	c := Config{
		APIBase:     APIBase(get(siteURL, "RENO_API_URL")),
		SiteURL:     strings.TrimRight(siteURL, "/"),
		SSHHost:     get("reno.sh", "RENO_SSH_HOST", "TUI_SSH_HOST"),
		ListenHost:  get("0.0.0.0", "RENO_LISTEN_HOST"),
		HostKeyPath: get(defaultHostKeyPath(), "RENO_HOST_KEY_PATH"),
	}

	var err error
	if c.SSHPort, err = intVar(get, 2222, "RENO_SSH_PORT", "TUI_SSH_PORT"); err != nil {
		return c, err
	}
	if c.SSHPort < 1 || c.SSHPort > 65535 {
		return c, fmt.Errorf("RENO_SSH_PORT out of range: %d", c.SSHPort)
	}
	if c.MaxSessions, err = intVar(get, 200, "RENO_MAX_SESSIONS"); err != nil {
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
		v := get("", d.key)
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

func intVar(get func(string, ...string) string, def int, keys ...string) (int, error) {
	v := get("", keys...)
	if v == "" {
		return def, nil
	}
	n, err := strconv.Atoi(v)
	if err != nil {
		return 0, fmt.Errorf("%s: %w", keys[0], err)
	}
	return n, nil
}

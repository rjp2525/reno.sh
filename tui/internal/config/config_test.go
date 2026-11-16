package config

import (
	"testing"
	"time"
)

func lookup(m map[string]string) func(string) (string, bool) {
	return func(k string) (string, bool) { v, ok := m[k]; return v, ok }
}

func TestDefaults(t *testing.T) {
	c, err := FromLookup(lookup(nil))
	if err != nil {
		t.Fatal(err)
	}
	if c.APIBase != "https://reno.sh/api/v1" || c.SSHPort != 2222 || c.SiteURL != "https://reno.sh" ||
		c.CacheTTL != time.Minute || c.IdleTimeout != 10*time.Minute || c.MaxSessions <= 0 {
		t.Fatalf("defaults: %+v", c)
	}
}

func TestOverrides(t *testing.T) {
	c, err := FromLookup(lookup(map[string]string{
		"RENO_API_URL":       "http://app/",
		"RENO_SSH_PORT":      "2022",
		"RENO_HOST_KEY_PATH": "/data/host_ed25519",
		"RENO_SITE_URL":      "https://example.com/",
		"RENO_IDLE_TIMEOUT":  "5m",
	}))
	if err != nil {
		t.Fatal(err)
	}
	if c.APIBase != "http://app/api/v1" || c.SSHPort != 2022 || c.HostKeyPath != "/data/host_ed25519" ||
		c.SiteURL != "https://example.com" || c.IdleTimeout != 5*time.Minute {
		t.Fatalf("overrides: %+v", c)
	}
	if APIBase("https://reno.sh/api/v1/") != "https://reno.sh/api/v1" {
		t.Fatal("existing /api/v1 suffix should be kept")
	}
	if _, err := FromLookup(lookup(map[string]string{"RENO_SSH_PORT": "nope"})); err == nil {
		t.Fatal("bad port should error")
	}
	if _, err := FromLookup(lookup(map[string]string{"RENO_CACHE_TTL": "soon"})); err == nil {
		t.Fatal("bad duration should error")
	}
}

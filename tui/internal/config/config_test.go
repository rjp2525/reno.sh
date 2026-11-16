package config

import (
	"os"
	"path/filepath"
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

func TestFallsBackToLaravelEnvNames(t *testing.T) {
	c, err := FromLookup(lookup(map[string]string{
		"APP_URL":      "https://staging.reno.sh/",
		"TUI_SSH_HOST": "staging.reno.sh",
		"TUI_SSH_PORT": "2223",
	}))
	if err != nil {
		t.Fatal(err)
	}
	if c.SiteURL != "https://staging.reno.sh" || c.APIBase != "https://staging.reno.sh/api/v1" ||
		c.SSHHost != "staging.reno.sh" || c.SSHPort != 2223 {
		t.Fatalf("laravel fallbacks: %+v", c)
	}
	// RENO_* wins over the Laravel names.
	c, _ = FromLookup(lookup(map[string]string{"APP_URL": "https://a.test", "RENO_SITE_URL": "https://b.test", "TUI_SSH_PORT": "1", "RENO_SSH_PORT": "2022"}))
	if c.SiteURL != "https://b.test" || c.SSHPort != 2022 {
		t.Fatalf("precedence: %+v", c)
	}
}

func TestReadEnvFile(t *testing.T) {
	path := filepath.Join(t.TempDir(), ".env")
	content := "# comment\nAPP_URL=https://reno.sh\nexport TUI_SSH_PORT=2222\nMAIL_FROM_NAME=\"${APP_NAME}\"\nAPP_NAME='Reno Philibert'\nX=1 # trailing\nnot a var\n\n"
	if err := os.WriteFile(path, []byte(content), 0o600); err != nil {
		t.Fatal(err)
	}
	vars, err := ReadEnvFile(path)
	if err != nil {
		t.Fatal(err)
	}
	want := map[string]string{"APP_URL": "https://reno.sh", "TUI_SSH_PORT": "2222", "MAIL_FROM_NAME": "${APP_NAME}", "APP_NAME": "Reno Philibert", "X": "1"}
	for k, v := range want {
		if vars[k] != v {
			t.Errorf("%s = %q, want %q", k, vars[k], v)
		}
	}
	if len(vars) != len(want) {
		t.Errorf("unexpected keys: %v", vars)
	}
}

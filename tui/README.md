# reno.sh over SSH

A terminal version of [reno.sh](https://reno.sh) for people who live in a shell:

```sh
ssh reno.sh -p 2222
```

It is a Go app built on Charm's [Wish](https://github.com/charmbracelet/wish) (SSH server),
[Bubble Tea](https://github.com/charmbracelet/bubbletea), [Bubbles](https://github.com/charmbracelet/bubbles),
[Lip Gloss](https://github.com/charmbracelet/lipgloss) and [Glamour](https://github.com/charmbracelet/glamour).
All content comes from the site's public JSON API (`docs/api.md`, `/api/v1`), so nothing has to be
deployed twice. The terminal UI follows the site's code-editor look: the `_hello`, `_about`,
`_projects`, `_photography`, `_blog` and `_contact-me` sections, with `#43D9AD`, `#FEA55F` and
`#4D5BCE` accents.

## Features

- **_hello**: name banner that adapts to the terminal width, title, tagline and a section menu.
- **_about**: professional, personal and hobbies pages rendered from Markdown, an experience
  timeline, skills grouped by category with proficiency bars, and education.
- **_projects**: list filtered by type (`←/→`) with `/` fuzzy search; the detail view shows
  Markdown, tech tags and links.
- **_blog**: category tabs, `/` fuzzy search, and posts rendered with Glamour in a scrollable
  view with a reading-progress bar and a table of contents (`t`).
- **_photography**: photos with an EXIF summary. The detail view draws the thumbnail as
  truecolor `▀` half blocks, using only the Go standard library's JPEG/PNG/GIF decoders, and
  lists the EXIF data, print options with prices, and the web link for ordering.
- **_contact-me**: a form for name, email, an optional subject and the message, validated and
  then `POST`ed to `/contact`.
- **Navigation**: `tab`/`shift+tab` or `1`-`6` switch sections, `↑/↓` or `j/k` move, `enter`
  opens, `q`/`esc` go back, `?` shows help and `ctrl+c` quits. The mouse wheel scrolls, clicking
  a tab switches to it, and the layout follows window resizes.
- **Themes**: the light or dark palette is picked from the terminal's background colour. Colours
  are downsampled to 256 or 16 colours where truecolor isn't available, including the image
  preview.
- **Error handling**: a spinner shows while content loads. If the API is down or slow, the
  screen explains what happened and `r` retries.

## Configuration

Variables come from the process environment, falling back to the Laravel `.env` in the working
directory (`RENO_ENV_FILE` to point elsewhere). Run it from the site root and it picks up `APP_URL`,
`TUI_SSH_HOST` and `TUI_SSH_PORT` with nothing else to configure.

| env | default | |
|---|---|---|
| `RENO_API_URL` | site URL | Site base URL; `/api/v1` is appended (unless already present) |
| `RENO_SITE_URL` | `APP_URL`, else `https://reno.sh` | Used for links shown to visitors |
| `RENO_SSH_PORT` | `TUI_SSH_PORT`, else `2222` | Listen port |
| `RENO_HOST_KEY_PATH` | `~/.reno-tui/host_ed25519` | Persistent ed25519 host key; generated on first start |
| `RENO_SSH_HOST` | `TUI_SSH_HOST`, else `reno.sh` | Hostname shown in hints (the API's `site.ssh` wins when present) |
| `RENO_LISTEN_HOST` | `0.0.0.0` | Bind address |
| `RENO_CACHE_TTL` | `60s` | Shared in-memory API cache TTL |
| `RENO_HTTP_TIMEOUT` | `10s` | API request timeout |
| `RENO_IDLE_TIMEOUT` | `10m` | Disconnect idle sessions |
| `RENO_MAX_SESSION` | `2h` | Absolute session lifetime |
| `RENO_MAX_SESSIONS` | `200` | Concurrent session cap |
| `RENO_SHUTDOWN_GRACE` | `15s` | How long SIGTERM waits for open sessions |

Durations use Go syntax (`90s`, `10m`, `2h`).

## Running locally

```sh
cd tui
go run . --local                                   # TUI in this terminal against https://reno.sh
RENO_API_URL=http://localhost:8088 go run . --local # against the local Docker site

go run .                                           # SSH server on :2222
ssh localhost -p 2222
```

`go run . --version` prints the build version, which you can set with `-ldflags "-X main.version=…"`.

### Tests

```sh
go vet ./...
go test ./...
```

The tests use an `httptest` fake of the API (`internal/api/apitest`, with fixtures that follow
`docs/api.md`). They cover the API client and cache, the screen models (navigation, search,
TOC, image, contact form, API down, idle timeout), the rendering helpers, and a real SSH
round-trip with `golang.org/x/crypto/ssh` (no-PTY hint, session cap, masked logs).

### Supply-chain gate

Every module in the build graph, direct or transitive, must have been published at least
7 days ago. To check:

```sh
./scripts/age-check.sh               # fails if any module is newer than 7 days
MIN_AGE_DAYS=14 ./scripts/age-check.sh
```

The script reads `go list -m -json all` (its `Time` field comes from the module proxy). Run it
in CI and after every `go get`. When adding or upgrading a module, pin an explicit version
(`go get module@vX.Y.Z`) and check its date first:
`curl https://proxy.golang.org/<module>/@v/<version>.info`.

## Docker

The repo's `compose.yml` already has a `tui` service (profile `tui`) that builds `./tui`:

```sh
docker compose --profile tui up -d tui
ssh localhost -p 2222          # or ${TUI_SSH_PORT}
```

Inside the compose network the API is reached at `http://app` (the Laravel container on port 80).
The host key lives in the `tui-data` volume at `/data/host_ed25519`, so it survives rebuilds.
Image paths (photo thumbnails) are absolute URLs built by Laravel from `APP_URL`, so they must
be reachable from the `tui` container. If they aren't, the preview says so and visitors still get
the web link.

Standalone:

```sh
docker build -t reno-sh/tui ./tui
docker run -d --name reno-tui -p 2222:2222 -v reno-tui-data:/data reno-sh/tui
```

The image is a multi-stage build: `golang:1.27.1-alpine` produces a static (`CGO_ENABLED=0`)
binary that runs on `alpine:3.22` as the unprivileged `reno` user (uid 10001). It exposes 2222
and has a TCP healthcheck.

## Deploying on Laravel Forge

The server's own OpenSSH stays on port 22 for you and Forge. The TUI is a separate process
on 2222.

1. **Build the binary.** Do this on the server (install Go 1.27.1) or in CI and upload the
   artifact:

   ```sh
   cd /home/forge/reno.sh/tui
   ./scripts/age-check.sh
   CGO_ENABLED=0 go build -trimpath -ldflags "-s -w -X main.version=$(git rev-parse --short HEAD)" \
     -o /home/forge/bin/reno-tui .
   ```

   You can add these lines to the site's deploy script so every deploy rebuilds the TUI. Restart
   the daemon afterwards (`sudo supervisorctl restart daemon-XXXXX:*`, or "Restart" in Forge).

2. **Create a daemon** under Server → Daemons:
   - Command:
     `env RENO_API_URL=https://reno.sh RENO_SITE_URL=https://reno.sh RENO_SSH_PORT=2222 RENO_HOST_KEY_PATH=/home/forge/.reno-tui/host_ed25519 /home/forge/bin/reno-tui`
   - User: `forge`
   - Directory: `/home/forge`
   - Processes: `1`
   - Stop signal: `SIGTERM`, with a stop wait of at least 20 seconds (the app waits up to
     `RENO_SHUTDOWN_GRACE` for sessions).

   Pointing `RENO_API_URL` at `http://127.0.0.1` with the right `Host` also works if you'd
   rather not go through the public edge.

3. **Open the firewall.** Under Server → Network, add a rule allowing TCP **2222** from any
   address. Leave 22 alone.

4. **DNS.** `reno.sh` must resolve to the server. If the site is behind a proxy/CDN that only
   forwards HTTP (e.g. Cloudflare orange-cloud), point a DNS-only record such as `ssh.reno.sh`
   at the server and set `RENO_SSH_HOST` to it.

5. **Back up the host key** in `/home/forge/.reno-tui/`. If it changes, returning visitors get
   a scary host-key warning.

Logs go to the daemon's log file (Forge → Daemons → Logs). They record connects and disconnects
with a truncated client network (`/24` IPv4, `/48` IPv6), terminal type and size, and session
duration. Usernames, keys, environment variables and keystrokes are never logged.

## Hardening

- Anonymous SSH: no shell, no port forwarding, no SFTP, no commands; only the TUI runs.
- Sessions without a PTY get a one-line hint and exit (`activeterm`).
- Idle timeout at both the connection level and in the app (10 min by default), a maximum
  session duration (2 h), a cap on concurrent sessions, and a per-IP connection rate limit.
- The API cache is shared by all sessions, and identical in-flight requests are collapsed into
  one, so a crowd of visitors costs the site about one request per resource per minute.
  Responses are size-limited (4 MiB JSON, 8 MiB images) and only `http(s)` image URLs are
  fetched.
- On SIGTERM/SIGINT the server stops accepting connections and lets open sessions finish
  (bounded by `RENO_SHUTDOWN_GRACE`).

## Layout

```
tui/
├── main.go                   flags (--local, --version), config, signals
├── internal/config           env parsing
├── internal/api              API types, client, TTL cache, singleflight
│   └── apitest               httptest fake API + fixtures (docs/api.md shapes)
├── internal/server           Wish server, middlewares (limits, logging, PTY hint)
├── internal/ui               root model + screens (home, about, projects, blog, photos, contact)
│   ├── components            banner font, half-block images, markdown/TOC, bars, money
│   └── theme                 palette + lipgloss styles (light/dark)
├── scripts/age-check.sh      supply-chain age gate (uses scripts/agecheck)
└── Dockerfile
```

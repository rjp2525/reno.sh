# Laravel Forge deploy script for reno.sh (zero-downtime site).
# Paste into Site → Deployments → Deploy Script. Forge expands the $MACRO() helpers and
# injects the $FORGE_* variables. A failing step leaves the previous release live.
#
# Set this to the SSH TUI daemon's ID (Server → Background processes) to restart it after
# each release; leave it empty if the daemon isn't set up.
TUI_DAEMON_ID=""

$CREATE_RELEASE()

cd $FORGE_RELEASE_DIRECTORY

# PHP dependencies, then the supply-chain guard: refuse a lockfile with packages < 7 days old.
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP scripts/composer-age-gate.php check

# pnpm enforces minimumReleaseAge from pnpm-workspace.yaml. Keep the lockfile frozen:
# falling back to a plain install would resolve new versions at deploy time.
pnpm install --frozen-lockfile
pnpm build   # runs wayfinder:generate, vue-tsc, then the client and SSR builds

$FORGE_PHP artisan storage:link
$FORGE_PHP artisan optimize
$FORGE_PHP artisan filament:optimize
$FORGE_PHP artisan migrate --force

# SSH TUI: every release is a fresh checkout, so build its binary each time (if Go is installed).
if command -v go >/dev/null 2>&1; then
    (cd tui && CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o bin/reno-tui .)
fi

$ACTIVATE_RELEASE()

$RESTART_QUEUES()

# Stop reno.sh's SSR server (port VITE_SSR_PORT only); its daemon restarts it on the new bundle.
$FORGE_PHP artisan inertia:stop-ssr || true

if [ -n "$TUI_DAEMON_ID" ]; then
    sudo -S supervisorctl restart "daemon-$TUI_DAEMON_ID:*"
fi

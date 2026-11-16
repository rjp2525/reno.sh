# Laravel Forge deploy script for reno.sh
# Paste into Site → Deployments → Deploy Script (Forge substitutes $FORGE_* vars).
set -e

cd $FORGE_SITE_PATH
git pull origin $FORGE_SITE_BRANCH

# Supply-chain guard: refuse to deploy a lockfile containing packages < 7 days old.
$FORGE_COMPOSER install --no-dev --no-interaction --prefer-dist --optimize-autoloader
$FORGE_PHP scripts/composer-age-gate.php check

# pnpm enforces minimumReleaseAge from pnpm-workspace.yaml.
corepack enable pnpm >/dev/null 2>&1 || true
pnpm install --frozen-lockfile
pnpm build

( flock -w 10 9 || exit 1
    echo 'Restarting FPM...'; sudo -S service $FORGE_PHP_FPM reload ) 9>/tmp/fpmlock

if [ -f artisan ]; then
    $FORGE_PHP artisan migrate --force
    $FORGE_PHP artisan storage:link || true
    $FORGE_PHP artisan optimize
    $FORGE_PHP artisan filament:optimize
    $FORGE_PHP artisan queue:restart
fi

# SSH TUI (Forge daemon "reno-tui" runs ./tui/bin/reno-tui); build on first deploy, then only when tui/ changed.
if [ ! -x tui/bin/reno-tui ] || git diff --name-only HEAD@{1} HEAD 2>/dev/null | grep -q '^tui/'; then
    (cd tui && CGO_ENABLED=0 go build -trimpath -ldflags="-s -w" -o bin/reno-tui .)
    sudo -S supervisorctl restart daemon-reno-tui:* || true
fi

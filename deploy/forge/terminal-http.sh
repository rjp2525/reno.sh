#!/usr/bin/env bash
# Forge recipe (run as root): let curl/wget/HTTPie get the terminal site on plain http://reno.sh
# instead of a 301. Browsers keep the HTTPS redirect.
#
# Forge puts the port-80 -> HTTPS redirect in an include under /etc/nginx/forge-conf/ that the
# site's editable nginx config can't override, so this patches that file in place. It's idempotent,
# validates with `nginx -t` and restores the original on failure. Re-run it if Forge rewrites the
# file (for example after reinstalling the certificate).
set -euo pipefail

DOMAIN="${DOMAIN:-reno.sh}"
CONF_ROOT="${CONF_ROOT:-/etc/nginx/forge-conf}"
MARKER="# reno.sh terminal-http"
# Backups live outside forge-conf: nginx includes every file in those before/ directories.
BACKUP_DIR="${BACKUP_DIR:-/root/nginx-terminal-http-backups}"

# Port-80 server blocks for this domain that redirect to HTTPS.
mkdir -p "$BACKUP_DIR"
mapfile -t FILES < <(grep -rlE "return 301 https://" "$CONF_ROOT" 2>/dev/null \
    | xargs -r grep -lE "server_name[^;]*[ .]${DOMAIN//./\\.}[ ;]" \
    | xargs -r grep -lE "listen[^;]*\b80\b" || true)

if [ "${#FILES[@]}" -eq 0 ]; then
    echo "No port-80 redirect for ${DOMAIN} found under ${CONF_ROOT}. Port-80 server blocks:" >&2
    nginx -T 2>/dev/null | grep -nE "^# configuration file|listen[^;]*80|server_name|return 301" >&2 || true
    exit 1
fi

for f in "${FILES[@]}"; do
    if grep -q "$MARKER" "$f"; then
        echo "Already patched: $f"
        continue
    fi
    cp -p "$f" "$BACKUP_DIR/$(echo "$f" | tr / _)"
    perl -0pi -e '
        s{^([ \t]*)return 301 (https://\S+);}{$1'"$MARKER"'
$1location / {
$1    if (\$http_user_agent ~* "^(curl|wget|httpie|xh)/") {
$1        proxy_pass https://127.0.0.1;
$1        break;
$1    }
$1    return 301 $2;
$1}
$1proxy_set_header Host \$host;
$1proxy_ssl_server_name on;
$1proxy_ssl_name \$host;}m
    ' "$f"
    echo "Patched: $f"
done

if nginx -t; then
    service nginx reload
    echo "Done. Test with: curl http://${DOMAIN}"
else
    echo "nginx -t failed; restoring originals." >&2
    for f in "${FILES[@]}"; do
        b="$BACKUP_DIR/$(echo "$f" | tr / _)"
        [ -f "$b" ] && cp -p "$b" "$f"
    done
    nginx -t && service nginx reload
    exit 1
fi

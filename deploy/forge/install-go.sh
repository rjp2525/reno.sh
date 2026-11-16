#!/usr/bin/env bash
# Forge recipe: install (or upgrade) Go system-wide for building the SSH TUI.
# Run as: root. Re-run with a new GO_VERSION to upgrade; it replaces /usr/local/go in place.
#
# Supply-chain guards: the tarball's SHA-256 must match go.dev's published checksum, and the
# release must be at least MIN_AGE_DAYS old (same 7-day policy as composer/pnpm/Go modules).
set -euo pipefail

GO_VERSION="1.27.4"
MIN_AGE_DAYS=7

case "$(uname -m)" in
    x86_64) GO_ARCH=amd64 ;;
    aarch64 | arm64) GO_ARCH=arm64 ;;
    *) echo "Unsupported architecture: $(uname -m)" >&2; exit 1 ;;
esac

FILE="go${GO_VERSION}.linux-${GO_ARCH}.tar.gz"
URL="https://dl.google.com/go/${FILE}"

if [ -x /usr/local/go/bin/go ] && [ "$(/usr/local/go/bin/go env GOVERSION)" = "go${GO_VERSION}" ]; then
    echo "Go ${GO_VERSION} is already installed."
    exit 0
fi

# Published checksum from go.dev (stable and archived releases).
EXPECTED_SHA=$(curl -fsSL 'https://go.dev/dl/?mode=json&include=all' | python3 -c "
import json, sys
for r in json.load(sys.stdin):
    for f in r['files']:
        if f['filename'] == '${FILE}':
            print(f['sha256']); sys.exit()
")
[ -n "$EXPECTED_SHA" ] || { echo "go${GO_VERSION} (${GO_ARCH}) not found on go.dev" >&2; exit 1; }

# Release age from the archive's Last-Modified header.
RELEASED=$(curl -fsSI "$URL" | awk -F': ' 'tolower($1)=="last-modified" {print $2}' | tr -d '\r')
AGE_DAYS=$(( ( $(date +%s) - $(date -d "$RELEASED" +%s) ) / 86400 ))
if [ "$AGE_DAYS" -lt "$MIN_AGE_DAYS" ]; then
    echo "go${GO_VERSION} was released ${AGE_DAYS} day(s) ago (${RELEASED}); policy requires ${MIN_AGE_DAYS}." >&2
    echo "Re-run after $(date -d "$RELEASED + ${MIN_AGE_DAYS} days" +%F), or pin the previous patch release." >&2
    exit 1
fi

TMP=$(mktemp -d)
trap 'rm -rf "$TMP"' EXIT
curl -fsSL -o "$TMP/$FILE" "$URL"
echo "${EXPECTED_SHA}  $TMP/$FILE" | sha256sum -c -

rm -rf /usr/local/go
tar -C /usr/local -xzf "$TMP/$FILE"

# Put go on PATH for login shells, and link it where Forge's deploy and daemon shells look.
echo 'export PATH=$PATH:/usr/local/go/bin' > /etc/profile.d/go.sh
ln -sf /usr/local/go/bin/go /usr/local/bin/go
ln -sf /usr/local/go/bin/gofmt /usr/local/bin/gofmt

go version

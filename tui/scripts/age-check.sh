#!/usr/bin/env sh
# Supply-chain gate: fail if any module in the build graph (direct or
# transitive) was published less than MIN_AGE_DAYS ago (default 7).
#
#   ./scripts/age-check.sh            # from tui/
#   MIN_AGE_DAYS=14 ./scripts/age-check.sh
#
# Uses the `Time` field reported by `go list -m -json all`, which comes from
# the module proxy's .info metadata. Modules without a Time (the main module,
# replaced or local modules) are skipped.
set -eu

cd "$(dirname "$0")/.."

MIN_AGE_DAYS="${MIN_AGE_DAYS:-7}"

go list -m -json all | go run ./scripts/agecheck -days "$MIN_AGE_DAYS"

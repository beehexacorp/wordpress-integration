#!/usr/bin/env bash
# Run every tests/t_*.sh against the running harness site. Exit code 0 only if all assertions pass.
DIR=$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)
. "$DIR/lib.sh"

docker ps --format '{{.Names}}' | grep -qx hxtest-wp || { echo "harness is not running: run tools/test-env/up.sh" >&2; exit 2; }

for t in "$DIR"/tests/t_*.sh; do
  echo "== $(basename "$t")"
  . "$t" || { echo "FAIL $(basename "$t") errored out"; HX_FAIL=1; }
done
echo "-- $HX_PASS_COUNT passed, $HX_FAIL_COUNT failed"
[ $((HX_PASS_COUNT+HX_FAIL_COUNT)) -gt 0 ] || { echo "no assertions ran"; HX_FAIL=1; }
exit "$HX_FAIL"

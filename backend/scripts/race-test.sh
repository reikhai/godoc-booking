#!/usr/bin/env bash
#
# End-to-end concurrency test: fire N booking requests at the SAME slot
# simultaneously and assert exactly one succeeds (201) while the rest lose the
# race (409). This exercises the real HTTP stack + MySQL row locking, which the
# in-process PHPUnit suite cannot (a single PHP process can't issue truly
# parallel requests).
#
# Usage:
#   1. php artisan migrate:fresh --seed
#   2. php artisan serve --port=8099
#   3. ./scripts/race-test.sh [SLOT_ID] [CONCURRENCY] [BASE_URL]
#
set -euo pipefail

SLOT="${1:-1}"
N="${2:-20}"
BASE="${3:-http://127.0.0.1:8099}"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

echo "Firing $N concurrent booking requests at slot $SLOT ..."
for i in $(seq 1 "$N"); do
  curl -s -o /dev/null -w "%{http_code}\n" \
    -X POST "$BASE/api/bookings" \
    -H "Content-Type: application/json" \
    -d "{\"slot_id\":$SLOT,\"patient\":{\"name\":\"P$i\",\"email\":\"p$i@example.com\"}}" \
    >"$TMP/r_$i" &
done
wait

echo
echo "HTTP status distribution:"
cat "$TMP"/r_* | sort | uniq -c

created=$(grep -l '^201' "$TMP"/r_* | wc -l | tr -d ' ')
conflict=$(grep -l '^409' "$TMP"/r_* | wc -l | tr -d ' ')

echo
echo "201 Created : $created  (expected 1)"
echo "409 Conflict: $conflict  (expected $((N - 1)))"

if [[ "$created" -eq 1 ]]; then
  echo "PASS: exactly one booking won the slot."
  exit 0
else
  echo "FAIL: expected exactly one 201, got $created."
  exit 1
fi

#!/usr/bin/env bash
set -euo pipefail

PUBLIC_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)/public"
PORT=8088
PHP_HOST=127.0.0.1
PHP_PID=0

function cleanup() {
  if [ "$PHP_PID" -ne 0 ]; then
    kill "$PHP_PID" 2>/dev/null || true
  fi
}
trap cleanup EXIT

echo "Starting php -S $PHP_HOST:$PORT ..."
pushd "$PUBLIC_DIR" > /dev/null || { echo "Failed to enter public dir"; exit 1; }
php -S "$PHP_HOST:$PORT" > /dev/null 2>&1 &
PHP_PID=$!
popd > /dev/null
echo "Started php server with PID $PHP_PID"

echo "Waiting for server to start..."
for i in {1..10}; do
  if curl -s -o /dev/null -w "%{http_code}" "http://$PHP_HOST:$PORT/swap.html" | grep -q "200"; then
    echo "Server ready"
    break
  fi
  sleep 0.5
done

if ! curl -s -o /dev/null -w "%{http_code}" "http://$PHP_HOST:$PORT/swap.html" | grep -q "200"; then
  echo "Server didn't start or swap.html missing"
  exit 2
fi

echo "Test 1: GET lite program-id-to-label"
code=$(curl -s -o /dev/null -w "%{http_code}" "http://$PHP_HOST:$PORT/api-proxy.php?target=lite&path=program-id-to-label")
echo "HTTP code: $code"
if [[ "$code" != "200" && "$code" != "304" ]]; then
  echo "FAILED: lite program-id-to-label expected 200/304 got $code"
  exit 3
fi

echo "Test 2: GET ultra order (allowed path should be forwarded; code may vary)"
code=$(curl -s -o /dev/null -w "%{http_code}" "http://$PHP_HOST:$PORT/api-proxy.php?target=ultra&path=order")
echo "HTTP code: $code"
if [[ "$code" == "403" || "$code" == "405" ]]; then
  echo "FAILED: ultra order was blocked unexpectedly with $code"
  exit 4
fi

echo "Test 3: Forbidden path should return 403"
code=$(curl -s -o /dev/null -w "%{http_code}" "http://$PHP_HOST:$PORT/api-proxy.php?target=ultra&path=admin")
echo "HTTP code: $code"
if [[ "$code" != "403" ]]; then
  echo "FAILED: expected 403 for admin path, got $code"
  exit 5
fi

echo "Test 4: POST execute should be accepted (body size limited)"
code=$(curl -s -o /dev/null -w "%{http_code}" -X POST "http://$PHP_HOST:$PORT/api-proxy.php?target=ultra&path=execute" -H 'Content-Type: application/json' -d '{}')
echo "HTTP code: $code"
if [[ "$code" == "403" || "$code" == "405" ]]; then
  echo "FAILED: POST execute blocked unexpectedly with $code"
  exit 6
fi

echo "All tests passed"
exit 0

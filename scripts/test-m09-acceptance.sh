#!/usr/bin/env bash
# M09 自动测试验收：App Jest + Backend 订单/支付 Feature 测试
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

echo "==> M09 App unit tests"
cd app
npm test -- --testPathPattern='orders|pollOrderStatus|paymentLauncher' --ci --coverage=false
cd "$ROOT"

echo ""
echo "==> M09 Backend API regression (Order + Payment)"

run_backend_tests() {
  docker exec king-shop-backend php artisan test --filter='OrderApiTest|PaymentApiTest'
}

if docker ps --format '{{.Names}}' | grep -qx 'king-shop-backend'; then
  run_backend_tests
elif docker compose ps backend --status running -q 2>/dev/null | grep -q .; then
  docker compose exec -T backend php artisan test --filter='OrderApiTest|PaymentApiTest'
else
  ./scripts/docker-test.sh --filter='OrderApiTest|PaymentApiTest'
fi

echo ""
echo "✅ M09 automated acceptance passed"

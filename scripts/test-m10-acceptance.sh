#!/usr/bin/env bash
# scripts/test-m10-acceptance.sh
set -euo pipefail
cd "$(dirname "$0")/.."
./scripts/docker-test.sh --filter=OrderApiTest
cd app && npm run test:m10
echo "M10 acceptance tests passed"

#!/usr/bin/env bash
# king-shop — 在 Docker backend 容器内运行 PHPUnit
# 用法:
#   ./scripts/docker-test.sh                    # 全量测试
#   ./scripts/docker-test.sh --filter=M00       # 按模块名过滤
#   ./scripts/docker-test.sh --filter=HealthCheckTest
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[docker-test]${NC} $*"; }
warn() { echo -e "${YELLOW}[docker-test]${NC} $*"; }
err()  { echo -e "${RED}[docker-test]${NC} $*" >&2; }

if ! docker info >/dev/null 2>&1; then
    err "Docker 未运行，请先启动 Docker Desktop"
    exit 1
fi

if ! docker compose ps --status running backend 2>/dev/null | grep -q backend; then
    warn "backend 容器未运行，尝试启动..."
    ./scripts/dev-up.sh
fi

FILTER=""
if [ "${1:-}" = "--filter" ] && [ -n "${2:-}" ]; then
    FILTER="$2"
    shift 2
fi

log "PHP 版本:"
docker compose exec -T backend php -v | head -1

log "清除配置缓存（避免测试误连 MySQL 清空 king_shop）..."
docker compose exec -T backend php artisan config:clear >/dev/null
docker compose exec -T backend php artisan route:clear >/dev/null

log "运行测试..."
if [ -n "$FILTER" ]; then
    docker compose exec -T backend php artisan test --filter="$FILTER" "$@"
else
    docker compose exec -T backend php artisan test "$@"
fi

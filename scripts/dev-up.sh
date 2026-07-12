#!/usr/bin/env bash
# king-shop 一键启动开发环境
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[dev-up]${NC} $*"; }
warn() { echo -e "${YELLOW}[dev-up]${NC} $*"; }
err()  { echo -e "${RED}[dev-up]${NC} $*" >&2; }

# 检查 Docker
if ! docker info >/dev/null 2>&1; then
    err "Docker 未运行，请先启动 Docker Desktop"
    exit 1
fi

# 构建自有 PHP 基础镜像（若不存在）
if ! docker image inspect king-shop/php:8.4.3-cli >/dev/null 2>&1; then
    log "构建自有 PHP 基础镜像..."
    "$ROOT/scripts/build-php-base.sh"
fi

log "启动服务 (mysql + redis + backend)..."
docker compose up -d --build

log "等待服务就绪..."
MAX_WAIT=120
ELAPSED=0

while [ $ELAPSED -lt $MAX_WAIT ]; do
    MYSQL_OK=$(docker inspect --format='{{.State.Health.Status}}' king-shop-mysql 2>/dev/null || echo "missing")
    REDIS_OK=$(docker inspect --format='{{.State.Health.Status}}' king-shop-redis 2>/dev/null || echo "missing")
    BACKEND_OK=$(docker inspect --format='{{.State.Status}}' king-shop-backend 2>/dev/null || echo "missing")

    if [ "$MYSQL_OK" = "healthy" ] && [ "$REDIS_OK" = "healthy" ] && [ "$BACKEND_OK" = "running" ]; then
        # 再等 backend 完成 composer install + migrate
        if docker compose logs backend 2>/dev/null | grep -q "Laravel backend ready"; then
            break
        fi
    fi
    sleep 3
    ELAPSED=$((ELAPSED + 3))
    printf "."
done
echo ""

log "服务状态："
docker compose ps

# 健康检查
log "检查 API..."
if curl -sf http://localhost:8000/api/v1/health >/dev/null 2>&1; then
    log "✓ API 正常: http://localhost:8000/api/v1/health"
    curl -s http://localhost:8000/api/v1/health | head -c 200
    echo ""
else
    warn "API 尚未就绪，查看日志: docker compose logs -f backend"
    docker compose logs --tail=20 backend
fi

echo ""
log "常用命令:"
echo "  查看日志:  ./scripts/dev-logs.sh"
echo "  停止服务:  ./scripts/dev-down.sh"
echo "  跑测试:    docker compose exec backend php artisan test"

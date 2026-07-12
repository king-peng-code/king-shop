#!/usr/bin/env bash
# 生产环境健康检查 — 全部通过时 exit 0
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

COMPOSE_FILE="docker-compose.prod.yml"
ENV_FILE=".env.prod"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

ok()   { echo -e "${GREEN}[health]${NC} ✓ $*"; }
warn() { echo -e "${YELLOW}[health]${NC} ✗ $*"; }
info() { echo -e "${GREEN}[health]${NC} $*"; }

FAIL=0
mark_fail() { FAIL=1; warn "$1"; }

HTTP_PORT="${HTTP_PORT:-80}"
CHECK_URL="${CHECK_URL:-}"

if [ -f "$ENV_FILE" ]; then
    set -a
    # shellcheck disable=SC1090
    source "$ENV_FILE"
    set +a
    HTTP_PORT="${HTTP_PORT:-80}"
    CHECK_URL="${CHECK_URL:-http://localhost:${HTTP_PORT}}"
else
    CHECK_URL="${CHECK_URL:-http://localhost:${HTTP_PORT}}"
    warn ".env.prod 不存在，使用默认 CHECK_URL=${CHECK_URL}"
fi

API_URL="${CHECK_URL%/}/api/v1/health"
FRONTEND_URL="${CHECK_URL%/}/"

info "检查生产容器状态..."

REQUIRED_CONTAINERS=(king-shop-mysql king-shop-redis king-shop-backend king-shop-nginx)

for name in "${REQUIRED_CONTAINERS[@]}"; do
    status=$(docker inspect --format='{{.State.Status}}' "$name" 2>/dev/null || echo "missing")
    if [ "$status" != "running" ]; then
        mark_fail "容器 ${name} 状态异常: ${status}"
        continue
    fi
    ok "容器 ${name} 运行中"
done

for name in king-shop-mysql king-shop-redis; do
    health=$(docker inspect --format='{{if .State.Health}}{{.State.Health.Status}}{{else}}none{{end}}' "$name" 2>/dev/null || echo "missing")
    if [ "$health" = "healthy" ]; then
        ok "容器 ${name} 健康检查通过"
    elif [ "$health" = "none" ]; then
        warn "容器 ${name} 未配置 healthcheck（跳过）"
    else
        mark_fail "容器 ${name} 健康检查未通过: ${health}"
    fi
done

info "检查 API: ${API_URL}"
if response=$(curl -sf --max-time 10 "$API_URL" 2>/dev/null); then
    if echo "$response" | grep -q '"code":0'; then
        ok "API 健康检查通过"
    else
        mark_fail "API 响应异常: ${response}"
    fi
else
    mark_fail "API 不可达: ${API_URL}"
fi

info "检查管理端静态资源: ${FRONTEND_URL}"
if curl -sf --max-time 10 -o /dev/null "$FRONTEND_URL"; then
    ok "管理端首页可访问"
else
    mark_fail "管理端首页不可达: ${FRONTEND_URL}"
fi

if [ ! -f frontend/dist/index.html ]; then
    mark_fail "frontend/dist/index.html 不存在，请先 npm run build"
else
    ok "frontend/dist/index.html 已构建"
fi

if [ "$FAIL" -eq 0 ]; then
    info "全部检查通过"
    exit 0
fi

warn "存在未通过的检查项"
exit 1

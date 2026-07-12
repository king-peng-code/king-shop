#!/usr/bin/env bash
# 单机生产部署脚本
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

log()  { echo -e "${GREEN}[deploy]${NC} $*"; }
warn() { echo -e "${YELLOW}[deploy]${NC} $*"; }
err()  { echo -e "${RED}[deploy]${NC} $*" >&2; }

# 检查 .env.prod
if [ ! -f .env.prod ]; then
    warn ".env.prod 不存在，从模板创建..."
    cp .env.prod.example .env.prod 2>/dev/null || cat > .env.prod <<'EOF'
MYSQL_ROOT_PASSWORD=your_root_password
MYSQL_DATABASE=king_shop
MYSQL_USER=king_shop
MYSQL_PASSWORD=your_db_password
APP_URL=http://your-server-ip
FRONTEND_URL=
APP_KEY=
HTTP_PORT=80
EOF
    err "请编辑 .env.prod 修改密码后重新运行"
    exit 1
fi

set -a
# shellcheck disable=SC1091
source .env.prod
set +a

# 默认 FRONTEND_URL 与 APP_URL 同域
if [ -z "${FRONTEND_URL:-}" ]; then
    FRONTEND_URL="$APP_URL"
    if grep -q '^FRONTEND_URL=' .env.prod; then
        sed -i.bak "s|^FRONTEND_URL=.*|FRONTEND_URL=${FRONTEND_URL}|" .env.prod && rm -f .env.prod.bak
    else
        echo "FRONTEND_URL=${FRONTEND_URL}" >> .env.prod
    fi
fi
export FRONTEND_URL

# 生产必须 APP_KEY
if [ -z "${APP_KEY:-}" ]; then
    warn "APP_KEY 未设置，自动生成并写入 .env.prod ..."
    APP_KEY="base64:$(openssl rand -base64 32)"
    echo "APP_KEY=${APP_KEY}" >> .env.prod
    export APP_KEY
    warn "已生成 APP_KEY，请备份 .env.prod"
fi

# 构建自有 PHP 基础镜像（若不存在）
if ! docker image inspect king-shop/php:8.4.3-fpm >/dev/null 2>&1; then
    log "构建自有 PHP 基础镜像..."
    docker compose -f docker-compose.prod.yml --profile base build php-base-fpm
fi

log "构建 backend 生产镜像..."
docker compose -f docker-compose.prod.yml --env-file .env.prod build backend

log "生成前端生产环境变量..."
API_BASE="${APP_URL%/}/api/v1"
cat > frontend/.env.production <<EOF
VITE_API_BASE_URL=${API_BASE}
VITE_APP_NAME=${VITE_APP_NAME:-King Shop}
EOF
log "  VITE_API_BASE_URL=${API_BASE}"

log "构建前端..."
cd frontend
npm ci
npm run build
cd "$ROOT"

log "启动生产环境 (mysql + redis + backend + nginx)..."
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d mysql redis backend nginx

log "等待服务就绪..."
MAX_WAIT=120
ELAPSED=0
while [ $ELAPSED -lt $MAX_WAIT ]; do
    MYSQL_OK=$(docker inspect --format='{{.State.Health.Status}}' king-shop-mysql 2>/dev/null || echo "missing")
    REDIS_OK=$(docker inspect --format='{{.State.Health.Status}}' king-shop-redis 2>/dev/null || echo "missing")
    BACKEND_OK=$(docker inspect --format='{{.State.Status}}' king-shop-backend 2>/dev/null || echo "missing")
    NGINX_OK=$(docker inspect --format='{{.State.Status}}' king-shop-nginx 2>/dev/null || echo "missing")

    if [ "$MYSQL_OK" = "healthy" ] && [ "$REDIS_OK" = "healthy" ] \
        && [ "$BACKEND_OK" = "running" ] && [ "$NGINX_OK" = "running" ]; then
        if curl -sf "http://localhost:${HTTP_PORT:-80}/api/v1/health" >/dev/null 2>&1; then
            break
        fi
    fi
    sleep 3
    ELAPSED=$((ELAPSED + 3))
    printf "."
done
echo ""

docker compose -f docker-compose.prod.yml ps

log "运行健康检查..."
chmod +x scripts/health-check.sh
if ./scripts/health-check.sh; then
    log "✓ 部署成功"
else
    warn "健康检查未全部通过，查看日志:"
    docker compose -f docker-compose.prod.yml logs --tail=30
    exit 1
fi

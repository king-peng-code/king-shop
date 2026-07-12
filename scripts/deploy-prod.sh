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
HTTP_PORT=80
EOF
    err "请编辑 .env.prod 修改密码后重新运行"
    exit 1
fi

set -a
source .env.prod
set +a

# 构建自有 PHP 基础镜像（若不存在）
if ! docker image inspect king-shop/php:8.4.3-fpm >/dev/null 2>&1; then
    log "构建自有 PHP 基础镜像..."
    docker compose -f docker-compose.prod.yml --profile base build php-base-fpm
fi

log "构建 backend 生产镜像..."
docker compose -f docker-compose.prod.yml --env-file .env.prod build backend

log "构建前端..."
cd frontend
npm ci
npm run build
cd "$ROOT"

log "启动生产环境 (mysql + redis + backend + nginx)..."
docker compose -f docker-compose.prod.yml --env-file .env.prod up -d mysql redis backend nginx

log "等待服务就绪..."
sleep 10
docker compose -f docker-compose.prod.yml ps

log "健康检查..."
if curl -sf "http://localhost:${HTTP_PORT:-80}/api/v1/health" >/dev/null 2>&1; then
    log "✓ 部署成功"
    curl -s "http://localhost:${HTTP_PORT:-80}/api/v1/health"
    echo ""
else
    warn "API 尚未就绪，查看日志:"
    docker compose -f docker-compose.prod.yml logs --tail=30
fi

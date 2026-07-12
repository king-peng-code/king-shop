#!/usr/bin/env bash
# 构建 king-shop 自有 PHP 基础镜像
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
PHP_DIR="$ROOT/docker/php"
source "$PHP_DIR/versions.env"

GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'
log()  { echo -e "${GREEN}[php-base]${NC} $*"; }
warn() { echo -e "${YELLOW}[php-base]${NC} $*"; }

PUSH=false
SAVE=false
REGISTRY="${REGISTRY:-}"

for arg in "$@"; do
    case $arg in
        --push) PUSH=true ;;
        --save) SAVE=true ;;
    esac
done

BUILD_ARGS=(
    --build-arg "PHP_VERSION=${PHP_VERSION}"
    --build-arg "COMPOSER_VERSION=${COMPOSER_VERSION}"
)

log "构建 CLI 镜像: ${IMAGE_CLI}"
docker build "${BUILD_ARGS[@]}" \
    -f "$PHP_DIR/Dockerfile.cli" \
    -t "${IMAGE_CLI}" \
    "$PHP_DIR"

log "构建 FPM 镜像: ${IMAGE_FPM}"
docker build "${BUILD_ARGS[@]}" \
    -f "$PHP_DIR/Dockerfile.fpm" \
    -t "${IMAGE_FPM}" \
    "$PHP_DIR"

log "基础镜像构建完成"
docker images | grep "king-shop/php"

if [ "$SAVE" = true ]; then
    SAVE_DIR="$ROOT/docker/images"
    mkdir -p "$SAVE_DIR"
    log "导出镜像到 ${SAVE_DIR}/"
    docker save "${IMAGE_CLI}" -o "${SAVE_DIR}/king-shop-php-${PHP_VERSION}-cli.tar"
    docker save "${IMAGE_FPM}" -o "${SAVE_DIR}/king-shop-php-${PHP_VERSION}-fpm.tar"
    log "✓ 已保存离线备份"
fi

if [ "$PUSH" = true ]; then
    if [ -z "$REGISTRY" ]; then
        warn "请设置 REGISTRY 环境变量，例: export REGISTRY=registry.cn-hangzhou.aliyuncs.com/your-ns"
        exit 1
    fi
    REMOTE_CLI="${REGISTRY}/king-shop/php:${PHP_VERSION}-cli"
    REMOTE_FPM="${REGISTRY}/king-shop/php:${PHP_VERSION}-fpm"
    docker tag "${IMAGE_CLI}" "${REMOTE_CLI}"
    docker tag "${IMAGE_FPM}" "${REMOTE_FPM}"
    docker push "${REMOTE_CLI}"
    docker push "${REMOTE_FPM}"
    log "✓ 已推送到 ${REGISTRY}"
fi

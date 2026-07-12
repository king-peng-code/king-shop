#!/usr/bin/env bash
# 重启 Docker Desktop 使镜像加速配置生效（macOS）
set -euo pipefail

echo "[docker-restart] 正在重启 Docker Desktop..."

osascript -e 'quit app "Docker"' 2>/dev/null || true

# 等待 Docker 完全退出
for i in $(seq 1 30); do
    if ! pgrep -x "Docker" >/dev/null 2>&1; then
        break
    fi
    sleep 1
done

open -a Docker

echo "[docker-restart] 等待 Docker 就绪..."
for i in $(seq 1 60); do
    if docker info >/dev/null 2>&1; then
        echo "[docker-restart] Docker 已就绪"
        docker info 2>/dev/null | grep -A6 "Registry Mirrors" || true
        exit 0
    fi
    sleep 2
done

echo "[docker-restart] Docker 启动超时，请手动打开 Docker Desktop"
exit 1

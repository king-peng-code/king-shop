#!/usr/bin/env bash
# 检查 king-shop Docker 构建状态与进度
set -uo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

timestamp() { date '+%Y-%m-%d %H:%M:%S'; }

echo "=== king-shop 构建状态检查 @ $(timestamp) ==="
echo

# 1. 构建进程
build_pids=$(pgrep -f 'php-base-cli' 2>/dev/null || true)
if [ -n "$build_pids" ]; then
  echo "[进程] 构建中 (PID: $(echo "$build_pids" | tr '\n' ' '))"
  oldest=$(ps -o etime= -p $(echo "$build_pids" | head -1) 2>/dev/null | xargs)
  echo "[进程] 已运行: ${oldest:-未知}"
else
  echo "[进程] 未发现 php-base-cli 构建进程"
fi
echo

# 2. 目标镜像
echo "[镜像] 目标: king-shop/php:8.4.3-cli"
if docker image inspect king-shop/php:8.4.3-cli >/dev/null 2>&1; then
  created=$(docker image inspect king-shop/php:8.4.3-cli --format '{{.Created}}' 2>/dev/null)
  size=$(docker image inspect king-shop/php:8.4.3-cli --format '{{.Size}}' 2>/dev/null)
  echo "[镜像] ✓ 已构建完成 (创建于 $created, 大小 ${size} bytes)"
  echo "[阶段] 4/4 完成 — PHP 基础镜像已就绪"
  exit 0
else
  echo "[镜像] ✗ 尚未完成"
fi
echo

# 3. 构建容器（buildkit 中间容器）
build_container=$(docker ps --filter 'ancestor=php:8.4.3-cli' --format '{{.Names}}' 2>/dev/null | head -1)
if [ -z "$build_container" ]; then
  build_container=$(docker ps --filter 'ancestor=php:8.4-cli' --format '{{.Names}}' 2>/dev/null | head -1)
fi

if [ -n "$build_container" ]; then
  uptime=$(docker ps --filter "name=$build_container" --format '{{.Status}}' 2>/dev/null)
  echo "[容器] 活跃构建容器: $build_container ($uptime)"

  procs=$(docker exec "$build_container" sh -c 'for f in /proc/[0-9]*/comm; do cat $f 2>/dev/null; done' 2>/dev/null | sort | uniq -c | sort -rn | head -8 || true)
  echo "[容器] 进程快照:"
  echo "$procs" | sed 's/^/  /'

  # 判断当前阶段
  if echo "$procs" | grep -q 'apt-get'; then
    echo "[阶段] 1/4 — 安装 apt 依赖 (git, libzip 等)"
  elif echo "$procs" | grep -qE 'docker-php-ext|configure|make|gcc'; then
    echo "[阶段] 2/4 — 编译 PHP 扩展"
  elif echo "$procs" | grep -qE 'pecl|phpize'; then
    echo "[阶段] 3/4 — 安装 pecl redis 扩展"
  else
    ext_check=$(docker exec "$build_container" sh -c 'php -m 2>/dev/null | grep -E "redis|pdo_mysql|zip" | tr "\n" " "' 2>/dev/null || true)
    if [ -n "$ext_check" ]; then
      echo "[阶段] 3/4 — 扩展已部分安装: $ext_check"
    else
      echo "[阶段] 1/4 — 初始化或等待网络下载"
    fi
  fi
else
  echo "[容器] 无活跃 PHP 构建容器"
  if [ -n "$build_pids" ]; then
    echo "[阶段] 1/4 — 构建启动中或拉取基础层"
  else
    echo "[阶段] 未在构建"
  fi
fi
echo

# 4. 网络活动
if [ -n "$build_container" ]; then
  net=$(docker stats --no-stream --format '{{.NetIO}}' "$build_container" 2>/dev/null || true)
  echo "[网络] 容器流量: ${net:-未知}"
fi

# 5. 结论
if [ -n "$build_pids" ]; then
  echo
  echo "[结论] 构建正常进行中，网络慢时会耗时较长"
  exit 1
else
  echo
  echo "[结论] 构建进程已结束，请检查是否成功或失败"
  exit 2
fi

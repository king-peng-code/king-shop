#!/usr/bin/env bash
# king-shop ngrok 隧道 — 暴露本地 backend 用于支付回调验证
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
cd "$ROOT"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()    { echo -e "${GREEN}[ngrok]${NC} $*"; }
warn()   { echo -e "${YELLOW}[ngrok]${NC} $*"; }
err()    { echo -e "${RED}[ngrok]${NC} $*" >&2; }
info()   { echo -e "${CYAN}[ngrok]${NC} $*"; }

# ─── 检查 ngrok ──────────────────────────────────────────────
if ! command -v ngrok &>/dev/null; then
    err "ngrok 未安装。请运行: brew install --cask ngrok 或从 https://ngrok.com/download 下载"
    exit 1
fi

# ─── 检查 authtoken ──────────────────────────────────────────
AUTH_CHECK=$(ngrok config check 2>&1 || true)
if echo "$AUTH_CHECK" | grep -qi "auth.*token\|authtoken\|missing\|not found"; then
    warn "未配置 ngrok authtoken！"
    echo ""
    info "请完成以下步骤："
    echo "  1. 打开 https://dashboard.ngrok.com/signup 注册免费账号"
    echo "  2. 登录后从 https://dashboard.ngrok.com/get-started/your-authtoken 复制 token"
    echo "  3. 运行: ngrok config add-authtoken <你的 token>"
    echo ""
    exit 1
fi

# ─── 检查 Docker backend ────────────────────────────────────
if ! docker inspect --format='{{.State.Status}}' king-shop-backend 2>/dev/null | grep -q running; then
    err "Backend 容器未运行。请先执行 ./scripts/dev-up.sh"
    exit 1
fi

# ─── 获取 APP_URL 更新提示 ──────────────────────────────────
NGROK_URL=""

log "启动 ngrok 隧道 → http://localhost:8000"
log "按 Ctrl+C 停止隧道"
echo ""

ngrok http 8000 --host-header=localhost:8000 2>&1 &
NGROK_PID=$!

# 等待 ngrok 就绪
sleep 3

# 通过 ngrok API 获取公网 URL
for i in {1..10}; do
    API_OUT=$(curl -sf http://127.0.0.1:4040/api/tunnels 2>/dev/null || true)
    NGROK_URL=$(echo "$API_OUT" | python3 -c "import sys,json; t=json.load(sys.stdin)['tunnels']; print([x['public_url'] for x in t if x['proto'] in ('https','http')][0])" 2>/dev/null || echo "")
    if [ -n "$NGROK_URL" ]; then
        break
    fi
    sleep 1
done

if [ -z "$NGROK_URL" ]; then
    warn "无法获取 ngrok URL，请检查 http://127.0.0.1:4040"
else
    echo ""
    info "══════════════════════════════════════════════════════════"
    info "  公网 URL:  ${NGROK_URL}"
    info "══════════════════════════════════════════════════════════"
    echo ""
    log "APK 下载："
    echo "  引导页:  ${NGROK_URL}/download-apk"
    echo "  直链:    ${NGROK_URL}/app-debug.apk"
    echo ""
    log "支付回调端点："
    echo "  支付宝: ${NGROK_URL}/api/v1/payments/notify/alipay"
    echo "  微信:   ${NGROK_URL}/api/v1/payments/notify/wechat"
    echo ""
    warn "重要："
    echo "  1. 在 .env 中更新 APP_URL 为: ${NGROK_URL}"
    echo "     (ngrok 每次重启 URL 会变，需要重新配置)"
    echo "  2. 然后在支付平台后台配置回调 URL 为上述端点"
    echo "  3. 用完后关闭隧道: kill ${NGROK_PID}"
    echo ""
fi

# 等待 ngrok 进程结束
wait $NGROK_PID

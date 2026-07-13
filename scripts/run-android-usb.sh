#!/usr/bin/env bash
# 用 USB 真机调试 Android App
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export JAVA_HOME="${JAVA_HOME:-$HOME/.local/jdk-17/Contents/Home}"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/platform-tools:$PATH"

# 1. 检查设备
echo "=== 检测 Android 设备 ==="
DEVICE=$(adb devices 2>/dev/null | grep -E '^[a-zA-Z0-9]+\s+device$' | head -1 | awk '{print $1}')
if [ -z "$DEVICE" ]; then
  echo ""
  echo "❌ 未检测到 Android 设备。"
  echo ""
  echo "请按以下步骤操作："
  echo "  1) 手机开启「开发者选项」和「USB 调试」"
  echo "    设置 → 关于手机 → 连续点击版本号 7 次"
  echo "    设置 → 系统 → 开发者选项 → USB 调试"
  echo "  2) 用 USB 连接 Mac"
  echo "  3) 手机上点击「允许 USB 调试」"
  echo "  4) 重新运行此脚本"
  echo ""
  exit 1
fi
echo "✅ 检测到设备: $DEVICE"

# 2. 清理旧的模拟器
echo ""
echo "=== 清理模拟器 ==="
pkill -f "emulator.*avd" 2>/dev/null || true
echo "done"

# 3. 端口转发
echo ""
echo "=== 端口转发 ==="
adb reverse --remove-all 2>/dev/null
adb reverse tcp:8000 tcp:8000
echo "  Port 8000 (Backend API) → OK"

# 4. 构建 Bundle（如需要）
echo ""
echo "=== 构建 JS Bundle ==="
cd "$ROOT/app"

if [ ! -f android/app/src/main/assets/index.android.bundle ] || [ "${1:-}" = "--rebuild" ]; then
  mkdir -p android/app/src/main/assets
  npx react-native bundle \
    --platform android \
    --dev true \
    --entry-file index.js \
    --bundle-output android/app/src/main/assets/index.android.bundle \
    --assets-dest android/app/src/main/res \
    --max-workers 2
  echo "Bundle 构建完成"
else
  echo "使用已有 Bundle"
fi

# 5. 构建 & 安装 APK
echo ""
echo "=== 安装 App ==="
cd "$ROOT/app/android"
./gradlew installDebug 2>&1 | tail -5
echo "✅ App 安装完成"

# 6. 启动 App
echo ""
echo "=== 启动 App ==="
adb shell am start -n com.kingshop/com.kingshop.MainActivity 2>&1
echo "✅ App 已启动"

echo ""
echo "=== 完成 ==="
echo "USB 设备: $DEVICE"
adb reverse --list

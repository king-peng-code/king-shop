#!/usr/bin/env bash
# Run React Native Android with env from ~/.zshrc
set -euo pipefail
ROOT="$(cd "$(dirname "$0")/.." && pwd)"

# Load king-shop android env
export JAVA_HOME="${JAVA_HOME:-$HOME/.local/jdk-17/Contents/Home}"
export ANDROID_HOME="${ANDROID_HOME:-$HOME/Library/Android/sdk}"
export ANDROID_SDK_ROOT="$ANDROID_HOME"
export PATH="$JAVA_HOME/bin:$ANDROID_HOME/emulator:$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"

if ! command -v java >/dev/null 2>&1; then
  echo "Java not found. Run: $ROOT/scripts/setup-android-mac.sh"
  exit 1
fi
if ! command -v adb >/dev/null 2>&1; then
  echo "adb not found. Run: $ROOT/scripts/setup-android-mac.sh"
  exit 1
fi

echo "java: $(java -version 2>&1 | head -1)"
echo "adb:  $(adb version | head -1)"

if ! adb devices | grep -qE 'device$'; then
  AVD="KingShop_API_34"
  if command -v emulator >/dev/null 2>&1 && emulator -list-avds 2>/dev/null | grep -qx "$AVD"; then
    echo "Starting emulator $AVD..."
    nohup emulator -avd "$AVD" >/tmp/king-shop-emulator.log 2>&1 &
    adb wait-for-device
  else
    echo ""
    echo "没有检测到手机或模拟器。请任选一种："
    echo "  1) USB 连接 Android 手机并开启 USB 调试"
    echo "  2) 安装 Android Studio 创建模拟器（见 docs/app-android-setup-mac.md）"
    echo ""
    adb devices -l
    exit 1
  fi
fi

"$ROOT/scripts/dev-up.sh"
cd "$ROOT/app"
npm run android

#!/usr/bin/env bash
# Install Android SDK components from Tencent mirror (China-friendly).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
SDK="$HOME/Library/Android/sdk"
CACHE="$HOME/.cache/king-shop"
JDK="$HOME/.local/jdk-17/Contents/Home"
MIRROR="https://mirrors.cloud.tencent.com/AndroidSDK"

mkdir -p "$CACHE" "$SDK"

log() { printf '\n==> %s\n' "$*"; }

fetch_unzip() {
  local file="$1"
  local dest="$2"
  local url="$MIRROR/$file"
  local cache_name
  cache_name="$(echo "$file" | tr '/' '_')"
  if [[ ! -f "$CACHE/$cache_name" ]]; then
    log "Download $file"
    curl -fL --retry 5 --progress-bar "$url" -o "$CACHE/$cache_name"
  else
    log "Use cache $file"
  fi
  mkdir -p "$dest"
  unzip -qo "$CACHE/$cache_name" -d "$dest"
}

log "Install platform-tools"
if [[ ! -x "$SDK/platform-tools/adb" ]]; then
  fetch_unzip "platform-tools_r35.0.2-darwin.zip" "$SDK/.unpack"
  rm -rf "$SDK/platform-tools"
  mv "$SDK/.unpack/platform-tools" "$SDK/platform-tools"
else
  log "platform-tools already installed"
fi

log "Install platform android-34"
if [[ ! -d "$SDK/platforms/android-34" ]]; then
  fetch_unzip "platform-34-ext7_r03.zip" "$SDK/.unpack"
  mkdir -p "$SDK/platforms"
  rm -rf "$SDK/platforms/android-34"
  mv "$SDK/.unpack/android-34" "$SDK/platforms/android-34" 2>/dev/null || mv "$SDK/.unpack"/android-* "$SDK/platforms/android-34"
else
  log "platform android-34 already installed"
fi

log "Install system image arm64-v8a"
if [[ ! -d "$SDK/system-images/android-34/google_apis/arm64-v8a" ]]; then
  fetch_unzip "sys-img/google_apis/arm64-v8a-34_r14.zip" "$SDK/.unpack"
  mkdir -p "$SDK/system-images/android-34/google_apis"
  rm -rf "$SDK/system-images/android-34/google_apis/arm64-v8a"
  mv "$SDK/.unpack/arm64-v8a" "$SDK/system-images/android-34/google_apis/arm64-v8a" 2>/dev/null || \
    mv "$SDK/.unpack"/* "$SDK/system-images/android-34/google_apis/arm64-v8a"
else
  log "system image already installed"
fi

rm -rf "$SDK/.unpack"

export JAVA_HOME="$JDK"
export ANDROID_HOME="$SDK"
export PATH="$JAVA_HOME/bin:$SDK/cmdline-tools/latest/bin:$PATH"

if [[ ! -d "$SDK/build-tools/34.0.0" ]]; then
  log "Install build-tools via sdkmanager (may take a while)..."
  yes | sdkmanager --licenses >/dev/null 2>&1 || true
  sdkmanager --install "build-tools;34.0.0" || sdkmanager --install "build-tools;35.0.0"
fi

if [[ ! -x "$SDK/emulator/emulator" ]]; then
  log "Install emulator via sdkmanager (may take a while)..."
  sdkmanager --install "emulator"
fi

log "Create AVD KingShop_API_34"
export PATH="$SDK/emulator:$SDK/platform-tools:$PATH"
if ! emulator -list-avds 2>/dev/null | grep -qx KingShop_API_34; then
  echo no | avdmanager create avd -n KingShop_API_34 \
    -k "system-images;android-34;google_apis;arm64-v8a" \
    -d pixel_6 --force
fi

# zshrc
if ! grep -q "king-shop android dev" "$HOME/.zshrc" 2>/dev/null; then
  cat >> "$HOME/.zshrc" <<EOF

# king-shop android dev
export JAVA_HOME="$JDK"
export ANDROID_HOME="$SDK"
export ANDROID_SDK_ROOT="$SDK"
export PATH="\$JAVA_HOME/bin:\$ANDROID_HOME/emulator:\$ANDROID_HOME/platform-tools:\$ANDROID_HOME/cmdline-tools/latest/bin:\$PATH"
EOF
fi

printf 'sdk.dir=%s\n' "$SDK" > "$ROOT/app/android/local.properties"

log "Verify"
"$SDK/platform-tools/adb" version | head -1
emulator -list-avds
java -version 2>&1 | head -1
log "Done. Open NEW terminal: cd $ROOT/app && npm run android"

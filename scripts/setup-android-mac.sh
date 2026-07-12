#!/usr/bin/env bash
# One-time Android dev environment setup for macOS (Apple Silicon).
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
JDK_DIR="$HOME/.local/jdk-17"
SDK_ROOT="$HOME/Library/Android/sdk"
CMDLINE_DIR="$SDK_ROOT/cmdline-tools/latest"
AVD_NAME="KingShop_API_34"
ZSHRC="$HOME/.zshrc"

log() { printf '\n==> %s\n' "$*"; }

append_zshrc() {
  local marker="# king-shop android dev"
  if grep -q "$marker" "$ZSHRC" 2>/dev/null; then
    log "Shell config already in $ZSHRC"
    return
  fi
  # Prefer Android Studio JBR if installed
  local java_home="$JDK_DIR/Contents/Home"
  if [[ -d "/Applications/Android Studio.app/Contents/jbr/Contents/Home" ]]; then
    java_home="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
  fi
  cat >> "$ZSHRC" <<EOF

$marker
export JAVA_HOME="$java_home"
export ANDROID_HOME="$SDK_ROOT"
export ANDROID_SDK_ROOT="$SDK_ROOT"
export PATH="\$JAVA_HOME/bin:\$ANDROID_HOME/emulator:\$ANDROID_HOME/platform-tools:\$ANDROID_HOME/cmdline-tools/latest/bin:\$PATH"
EOF
  log "Updated $ZSHRC"
}

install_jdk17() {
  if [[ -x "$JDK_DIR/Contents/Home/bin/java" ]]; then
    log "JDK 17 already at $JDK_DIR"
    return
  fi
  log "Downloading JDK 17 (清华镜像)..."
  mkdir -p "$HOME/.local"
  local tmp tarball
  tmp="$(mktemp -d)"
  tarball="$tmp/jdk17.tar.gz"
  curl -fL --retry 5 --connect-timeout 30 --progress-bar \
    "https://mirrors.tuna.tsinghua.edu.cn/Adoptium/17/jdk/aarch64/mac/OpenJDK17U-jdk_aarch64_mac_hotspot_17.0.19_10.tar.gz" \
    -o "$tarball"
  tar -xzf "$tarball" -C "$tmp"
  rm -rf "$JDK_DIR"
  mv "$tmp"/jdk-17* "$JDK_DIR"
  rm -rf "$tmp"
  "$JDK_DIR/Contents/Home/bin/java" -version
}

install_android_cmdline() {
  if [[ -x "$CMDLINE_DIR/bin/sdkmanager" ]]; then
    log "cmdline-tools already installed"
    return
  fi
  log "Downloading Android command-line tools..."
  mkdir -p "$SDK_ROOT/cmdline-tools"
  local tmp zip
  tmp="$(mktemp -d)"
  zip="$tmp/cmdline-tools.zip"
  curl -fL --retry 5 --connect-timeout 30 --progress-bar \
    "https://dl.google.com/android/repository/commandlinetools-mac-11076708_latest.zip" \
    -o "$zip"
  unzip -q "$zip" -d "$tmp"
  rm -rf "$CMDLINE_DIR"
  mkdir -p "$CMDLINE_DIR"
  mv "$tmp/cmdline-tools/"* "$CMDLINE_DIR/"
  rm -rf "$tmp"
}

install_sdk_packages() {
  export JAVA_HOME="${JAVA_HOME:-$JDK_DIR/Contents/Home}"
  if [[ -d "/Applications/Android Studio.app/Contents/jbr/Contents/Home" ]]; then
    export JAVA_HOME="/Applications/Android Studio.app/Contents/jbr/Contents/Home"
  fi
  export ANDROID_HOME="$SDK_ROOT"
  export PATH="$JAVA_HOME/bin:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"

  log "Accepting SDK licenses..."
  yes | sdkmanager --licenses >/dev/null || true

  log "Installing SDK packages (5-15 min)..."
  sdkmanager --install \
    "platform-tools" \
    "emulator" \
    "platforms;android-34" \
    "build-tools;34.0.0" \
    "system-images;android-34;google_apis;arm64-v8a"
}

create_avd() {
  export JAVA_HOME="${JAVA_HOME:-$JDK_DIR/Contents/Home}"
  export ANDROID_HOME="$SDK_ROOT"
  export PATH="$JAVA_HOME/bin:$ANDROID_HOME/emulator:$ANDROID_HOME/platform-tools:$ANDROID_HOME/cmdline-tools/latest/bin:$PATH"

  if emulator -list-avds 2>/dev/null | grep -qx "$AVD_NAME"; then
    log "AVD $AVD_NAME exists"
    return
  fi
  log "Creating emulator $AVD_NAME..."
  echo no | avdmanager create avd \
    -n "$AVD_NAME" \
    -k "system-images;android-34;google_apis;arm64-v8a" \
    -d pixel_6 \
    --force
}

write_local_properties() {
  printf 'sdk.dir=%s\n' "$SDK_ROOT" > "$ROOT/app/android/local.properties"
  log "Wrote app/android/local.properties"
}

main() {
  install_jdk17
  install_android_cmdline
  install_sdk_packages
  create_avd
  append_zshrc
  write_local_properties
  log "Setup complete. Open a NEW terminal, then: cd $ROOT/app && npm run android"
}

main "$@"

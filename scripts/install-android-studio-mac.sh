#!/usr/bin/env bash
# Download and install Android Studio to /Applications (macOS arm64).
set -euo pipefail

DMG_URL="https://redirector.gvt1.com/edgedl/android/studio/install/2024.3.2.15/android-studio-2024.3.2.15-mac_arm.dmg"
CACHE="$HOME/.cache/king-shop"
DMG="$CACHE/android-studio-mac_arm.dmg"
MOUNT="/Volumes/Android Studio"

mkdir -p "$CACHE"

if [[ -d "/Applications/Android Studio.app" ]]; then
  echo "[install-as] Android Studio already installed"
  exit 0
fi

echo "[install-as] Downloading Android Studio (~1.2GB, please wait)..."
curl -fL --retry 3 --continue-at - --progress-bar "$DMG_URL" -o "$DMG"

echo "[install-as] Mounting DMG..."
hdiutil attach "$DMG" -nobrowse -quiet

echo "[install-as] Installing to /Applications..."
rm -rf "/Applications/Android Studio.app"
cp -R "$MOUNT/Android Studio.app" /Applications/

hdiutil detach "$MOUNT" -quiet
echo "[install-as] Done. Open Android Studio once to finish SDK setup (Standard install)."

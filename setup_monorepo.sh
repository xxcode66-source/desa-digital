#!/usr/bin/env bash
#
# setup_monorepo.sh — bootstrap nyata Aplikasi Desa Digital (Fase 0).
#
# Menyiapkan dua sisi repo dari checkout bersih:
#   1. backend/  : composer install, .env, APP key, migrate + seed (sqlite default).
#   2. android-app/ : Gradle wrapper sync (assembleDebug opsional).
#
# Pemakaian:
#   bash setup_monorepo.sh            # setup backend + validasi android (tanpa build APK)
#   bash setup_monorepo.sh --android  # sekalian build APK debug
#
# Asumsi toolchain: PHP >= 8.2 + Composer, JDK 17+ (JBR Android Studio), Android SDK.
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
BUILD_ANDROID="false"
[[ "${1:-}" == "--android" ]] && BUILD_ANDROID="true"

info() { printf '\n\033[1;36m==> %s\033[0m\n' "$1"; }

# ---------------------------------------------------------------------------
# 1. Backend (Laravel)
# ---------------------------------------------------------------------------
info "Setup backend Laravel"
cd "$ROOT_DIR/backend"

if [[ ! -f composer.json ]]; then
  echo "composer.json tidak ditemukan di backend/ — henti." >&2
  exit 1
fi

info "composer install"
composer install --no-interaction --prefer-dist

if [[ ! -f .env ]]; then
  info "Membuat .env dari .env.example (sqlite)"
  cp .env.example .env
  # Default sqlite agar langsung bisa migrate tanpa DB server.
  sed -i.bak 's/^DB_CONNECTION=.*/DB_CONNECTION=sqlite/' .env && rm -f .env.bak
  grep -q '^DB_DATABASE=' .env || echo 'DB_DATABASE=database/database.sqlite' >> .env
fi

# pastikan file sqlite ada
touch database/database.sqlite

info "php artisan key:generate"
php artisan key:generate --force

info "php artisan migrate --seed"
php artisan migrate:fresh --seed --force

info "Publikasi aset Filament (panel admin)"
php artisan filament:assets

info "Cek route API"
php artisan route:list --path=api || true

info "Smoke test phpunit"
php artisan test

# ---------------------------------------------------------------------------
# 2. Android (Gradle)
# ---------------------------------------------------------------------------
cd "$ROOT_DIR"

if [[ "$BUILD_ANDROID" == "true" ]]; then
  info "Build APK debug (android-app)"
  if [[ -z "${JAVA_HOME:-}" && -d "/mnt/c/Program Files/Android/Android Studio/jbr" ]]; then
    export JAVA_HOME="/mnt/c/Program Files/Android/Android Studio/jbr"
  fi
  ( cd android-app && ./gradlew.bat :app:assembleDebug --console=plain 2>/dev/null \
      || ./gradlew :app:assembleDebug --console=plain )
  echo "APK: android-app/app/build/outputs/apk/debug/app-debug.apk"
else
  info "Validasi struktur android-app (lewati build; pakai --android untuk build APK)"
  for f in settings.gradle.kts build.gradle.kts gradle/libs.versions.toml \
           app/build.gradle.kts app/src/main/AndroidManifest.xml; do
    [[ -f "android-app/$f" ]] && echo "  ok: $f" || { echo "  HILANG: $f" >&2; exit 1; }
  done
fi

info "Selesai — monorepo siap (Fase 0)."

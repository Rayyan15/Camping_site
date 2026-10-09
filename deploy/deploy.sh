#!/usr/bin/env bash
# Rilis aman: cadangan, maintenance mode, migrasi dengan konfirmasi, cache, restart worker.
# Rahasia hanya dibaca dari .env di server, tidak ada di skrip ini.
set -euo pipefail

APP_DIR="${APP_DIR:-$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)}"
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-composer}"
SEED_ROLES="${SEED_ROLES:-0}"   # 1 bila config/access.php berubah
SKIP_FRONTEND="${SKIP_FRONTEND:-0}"
LOG_FILE="storage/logs/deploy-$(date +%Y-%m-%d).log"

cd "$APP_DIR"
artisan() { "$PHP_BIN" artisan "$@"; }

is_down=0
# Tanpa trap, kegagalan setelah down membuat situs tertahan di mode maintenance.
bring_up() {
  if [ "$is_down" -eq 1 ]; then
    echo "Menjalankan 'artisan up' agar situs tidak tertahan."
    artisan up || true
  fi
}
trap bring_up EXIT

echo "== Cadangan database"
artisan app:backup-database

echo "== Maintenance mode"
artisan down --retry=60
is_down=1

echo "== Dependensi"
"$COMPOSER_BIN" install --no-dev -o --no-interaction
if [ "$SKIP_FRONTEND" != "1" ]; then
  npm ci
  npm run build
fi

echo "== Tinjau SQL migrasi"
artisan migrate --pretend

# Dua migrasi data tidak bisa di-rollback, jadi manusia harus melihat SQL dulu.
read -r -p "Lanjut menjalankan migrate --force? Ketik 'ya': " answer
if [ "$answer" != "ya" ]; then
  echo "Dibatalkan."
  exit 1
fi
artisan migrate --force

if [ "$SEED_ROLES" = "1" ]; then
  artisan db:seed --class=RoleSeeder --force
fi

artisan filament:upgrade
artisan storage:link || true
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache
artisan queue:restart

artisan up
is_down=0

mkdir -p "$(dirname "$LOG_FILE")"
{
  echo "== Deploy $(date '+%Y-%m-%d %H:%M:%S')"
  artisan migrate:status
} >> "$LOG_FILE"

echo "Selesai. Lakukan smoke test (lihat deploy/DEPLOY.md) dan cek cron schedule:run serta worker queue."

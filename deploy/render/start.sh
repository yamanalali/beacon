#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

# Laravel AES-256-CBC needs APP_KEY = "base64:" + 32 raw bytes (44 chars after prefix).
# Render's generateValue creates a random string that fails Encrypter boot.
if [[ -z "${APP_KEY:-}" || "${APP_KEY}" != base64:* ]]; then
  echo "WARN: APP_KEY missing or not a Laravel base64 key; generating a boot-time key." >&2
  echo "WARN: Set a stable key in the Render dashboard (php artisan key:generate --show) to keep sessions across deploys." >&2
  export APP_KEY="$(php -r 'echo "base64:".base64_encode(random_bytes(32));')"
fi

php artisan config:clear
php artisan migrate --force --no-interaction

if [[ "${SEED_DEMO:-false}" == "true" ]]; then
  php artisan db:seed --class=DemoSeeder --force --no-interaction || true
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan storage:link --force || true

exec /usr/bin/supervisord -n -c /etc/supervisord.conf

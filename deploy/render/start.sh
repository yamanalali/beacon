#!/usr/bin/env bash
set -euo pipefail

cd /var/www/html

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

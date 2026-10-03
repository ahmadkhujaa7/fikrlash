#!/usr/bin/env bash
# macOS / Linux uchun lokal o'rnatish (SQLite, MySQL shart emas)
set -e
cd "$(dirname "$0")"
php scripts/setup-local.php
composer install --no-interaction
php artisan migrate:fresh --seed --force
php artisan storage:link || true
npm install && npm run build
echo "Tayyor: php artisan serve  →  http://localhost:8000  (admin: +998900000001 / admin12345)"

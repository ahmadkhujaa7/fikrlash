#!/usr/bin/env bash
# Zero-downtime deploy: har bir reliz alohida papkada, "current" symlink almashtiriladi.
#   /var/www/fikrlash/releases/<timestamp>   — relizlar
#   /var/www/fikrlash/shared/.env            — umumiy .env
#   /var/www/fikrlash/shared/storage         — umumiy storage (yuklangan fayllar, loglar)
# Ishlatish: REPO=git@github.com:USER/fikrlash.git BRANCH=main ./deploy.sh
set -euo pipefail

APP=/var/www/fikrlash
REPO=${REPO:?REPO ni ko‘rsating}
BRANCH=${BRANCH:-main}
RELEASE=$APP/releases/$(date +%Y%m%d%H%M%S)

echo "→ Klonlash: $BRANCH"
git clone --depth 1 --branch "$BRANCH" "$REPO" "$RELEASE"
cd "$RELEASE"

echo "→ Shared fayllar"
ln -sfn $APP/shared/.env .env
rm -rf storage && ln -sfn $APP/shared/storage storage

echo "→ Bog‘liqliklar"
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
npm ci && npm run build && rm -rf node_modules

echo "→ Migratsiya va kesh"
php artisan migrate --force
php artisan storage:link
php artisan optimize           # config, route, view, event keshlari
php artisan filament:optimize  # Filament komponent keshlari

echo "→ Relizni yoqish"
ln -sfn "$RELEASE" $APP/current
sudo systemctl reload php8.3-fpm
php artisan queue:restart       # workerlar yangi kodni oladi

echo "→ Eski relizlarni tozalash (oxirgi 5 tasi qoladi)"
ls -1dt $APP/releases/* | tail -n +6 | xargs -r rm -rf

echo "✓ Deploy tugadi: $RELEASE"

#!/usr/bin/env sh
set -e

if [ -f artisan ]; then
  if [ ! -f .env ] && [ -f .env.example ]; then
    cp .env.example .env
  fi

  if [ ! -d vendor ] || [ ! -f vendor/autoload.php ]; then
    composer install --no-interaction --prefer-dist
  fi

  if [ -f .env ] && ! grep -q '^APP_KEY=base64:' .env; then
    php artisan key:generate --force --no-interaction
  fi
fi

exec "$@"

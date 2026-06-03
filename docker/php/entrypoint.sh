#!/usr/bin/env bash
set -e

if [ ! -f .env ] && [ -f .env.docker.example ]; then
    cp .env.docker.example .env
fi

mkdir -p storage/framework/cache storage/framework/sessions storage/framework/views storage/logs bootstrap/cache
chown -R laravel:laravel storage bootstrap/cache 2>/dev/null || true

if [ -f artisan ]; then
    php artisan package:discover --ansi || true
fi

exec "$@"


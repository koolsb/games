# syntax=docker/dockerfile:1

# ---------------------------------------------------------------------------
# Stage 1 — Composer dependencies (no dev)
# Flux Pro is fetched from composer.fluxui.dev using a BuildKit secret so the
# license never lands in the image. CI mounts it as `flux_auth` (an auth.json).
# ---------------------------------------------------------------------------
FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN --mount=type=secret,id=flux_auth,target=/app/auth.json \
    composer install \
        --no-dev --no-scripts --prefer-dist --no-interaction --no-progress \
        --optimize-autoloader --ignore-platform-reqs
COPY . .
RUN composer dump-autoload --no-dev --optimize --no-scripts

# ---------------------------------------------------------------------------
# Stage 2 — Front-end build (Vite + Tailwind 4)
# ---------------------------------------------------------------------------
FROM node:22-bookworm-slim AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY . .
COPY --from=vendor /app/vendor ./vendor
RUN npm run build

# ---------------------------------------------------------------------------
# Stage 3 — Runtime (FrankenPHP, served on :8080 as non-root www-data)
# ---------------------------------------------------------------------------
FROM dunglas/frankenphp:php8.4 AS runtime
WORKDIR /app

# FrankenPHP serves /app/public; :8080 (no host) means plain HTTP, no auto-TLS.
ENV SERVER_NAME=:8080

RUN install-php-extensions intl opcache

COPY --from=vendor --chown=www-data:www-data /app/vendor ./vendor
COPY --chown=www-data:www-data . .
COPY --from=assets --chown=www-data:www-data /app/public/build ./public/build

# Writable runtime dirs for a non-root user (no DB, no PVC — all ephemeral).
RUN mkdir -p \
        storage/framework/sessions \
        storage/framework/views \
        storage/framework/cache/data \
        storage/logs \
        bootstrap/cache \
        /data /config \
    && chown -R www-data:www-data storage bootstrap/cache /data /config \
    && chmod -R ug+rwX storage bootstrap/cache

USER www-data
EXPOSE 8080

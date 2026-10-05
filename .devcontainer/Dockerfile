FROM mcr.microsoft.com/devcontainers/php:8.3

ENV XDEBUG_MODE=off

RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev libpq-dev libsqlite3-dev \
    && docker-php-ext-install intl pdo_pgsql pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

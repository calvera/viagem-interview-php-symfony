FROM dunglas/frankenphp:php8.5

ARG UID=1000
ARG GID=1000

RUN install-php-extensions pdo_mysql intl opcache zip \
    && apt-get update && apt-get install -y --no-install-recommends git unzip && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/app.ini /usr/local/etc/php/conf.d/app.ini

RUN groupadd -g ${GID} app && useradd -m -u ${UID} -g ${GID} app \
    && chown -R app:app /data/caddy /config/caddy

USER app
WORKDIR /app

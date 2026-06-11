ARG PHP_VERSION=8.5.7
ARG ALPINE_VERSION=3.24
ARG COMPOSER_VERSION=2.10.1

FROM composer:${COMPOSER_VERSION} AS composer-bin
FROM php:${PHP_VERSION}-fpm-alpine${ALPINE_VERSION} AS php-base

RUN apk add --no-cache --virtual .build-deps $PHPIZE_DEPS sqlite-dev \
	&& docker-php-ext-install pdo pdo_sqlite \
	&& apk del .build-deps \
	&& apk add --no-cache sqlite-libs

FROM php-base AS vendor

COPY --from=composer-bin /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader

FROM php-base AS deploy

ARG UID=1001
ARG GID=1001

RUN addgroup -g ${GID} app \
	&& adduser -D -u ${UID} -G app app \
	&& mkdir -p /var/www/backend \
	&& chown -R app:app /var/www/backend

WORKDIR /var/www/backend

COPY --from=vendor --chown=app:app /app/vendor ./vendor
COPY --chown=app:app . ./

USER app

CMD ["php-fpm"]

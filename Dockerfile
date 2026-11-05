# Beacon on Render — multi-stage: Vite assets + PHP-FPM/nginx
FROM node:22-alpine AS frontend
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js tsconfig*.json ./
COPY public ./public
RUN npm run build

FROM composer:2 AS vendor
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

FROM php:8.3-fpm-alpine

RUN apk add --no-cache bash nginx supervisor libpq libzip icu-libs freetype libjpeg-turbo libpng \
 && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS postgresql-dev libzip-dev icu-dev \
      freetype-dev libjpeg-turbo-dev libpng-dev \
 && docker-php-ext-configure gd --with-freetype --with-jpeg \
 && docker-php-ext-install pdo_pgsql pdo_mysql bcmath intl zip opcache gd \
 && apk del .build-deps

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN cp /usr/local/etc/php/php.ini-production /usr/local/etc/php/php.ini
COPY deploy/render/docker-vars.ini /usr/local/etc/php/conf.d/docker-vars.ini
COPY deploy/render/www-overrides.conf /usr/local/etc/php-fpm.d/zz-overrides.conf
COPY deploy/render/nginx-site.conf /etc/nginx/http.d/default.conf
COPY deploy/render/supervisord.conf /etc/supervisord.conf
COPY deploy/render/start.sh /start.sh
RUN chmod +x /start.sh

WORKDIR /var/www/html
COPY . .
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

RUN composer dump-autoload --optimize --no-dev \
 && chown -R www-data:www-data storage bootstrap/cache \
 && mkdir -p storage/framework/{cache,sessions,views} storage/logs storage/app/public \
 && chown -R www-data:www-data storage

ENV APP_ENV=staging \
    APP_DEBUG=false \
    LOG_CHANNEL=stderr \
    COMPOSER_ALLOW_SUPERUSER=1

EXPOSE 80
CMD ["/start.sh"]

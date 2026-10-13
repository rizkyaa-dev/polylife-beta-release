FROM php:8.2-fpm-alpine AS php-base
WORKDIR /app
RUN apk add --no-cache nginx supervisor curl libpng libjpeg-turbo libwebp freetype libzip oniguruma \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev libzip-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-webp --with-jpeg --with-freetype \
    && docker-php-ext-install -j2 pdo_mysql mbstring pcntl bcmath gd opcache zip \
    && apk del .build-deps

FROM php-base AS dependencies
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-scripts --no-interaction --prefer-dist --no-progress
COPY . .
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && composer dump-autoload --no-dev --classmap-authoritative --no-scripts \
    && php artisan package:discover --ansi

FROM node:22-alpine AS assets
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY public ./public
COPY vite.config.js tailwind.config.js postcss.config.js ./
RUN npm run build

FROM php-base AS runtime
ENV APP_ENV=production APP_DEBUG=false LOG_CHANNEL=stderr
COPY --from=dependencies --chown=www-data:www-data /app /app
COPY --from=assets --chown=www-data:www-data /app/public/build /app/public/build
COPY ["docs/quiz ppl/container/nginx.conf", "/etc/nginx/nginx.conf"]
COPY ["docs/quiz ppl/container/supervisord.conf", "/etc/supervisord.conf"]
COPY ["docs/quiz ppl/container/php-fpm.conf", "/usr/local/etc/php-fpm.d/zz-quiz.conf"]
RUN mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache \
    && chown -R www-data:www-data storage bootstrap/cache
USER www-data
EXPOSE 3000
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

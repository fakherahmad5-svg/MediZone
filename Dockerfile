FROM php:8.3-fpm-alpine

# ---- System dependencies (needed to build PHP extensions) ----
RUN apk add --no-cache \
        bash \
        curl \
        git \
        unzip \
        zip \
        freetype-dev \
        icu-dev \
        libjpeg-turbo-dev \
        libpng-dev \
        libwebp-dev \
        libzip-dev \
        oniguruma-dev \
        mysql-client \
    && apk add --no-cache --virtual .build-deps $PHPIZE_DEPS

# ---- PHP extensions required by Laravel ----
RUN docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mbstring \
        exif \
        pcntl \
        bcmath \
        gd \
        zip \
        intl \
        opcache \
    && apk del .build-deps

# ---- Composer (official binary, no need to install via curl) ----
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ---- PHP runtime tuning for local development ----
COPY docker/php/local.ini /usr/local/etc/php/conf.d/local.ini

# ---- Non-root user (matches host UID/GID to avoid permission issues on bind mounts) ----
ARG UID=1000
ARG GID=1000
RUN addgroup -g ${GID} laravel \
    && adduser -D -u ${UID} -G laravel laravel

WORKDIR /var/www/html

# ---- Dependency layer: copy only composer files first for build-cache efficiency ----
# This layer only re-runs when composer.json/composer.lock change, not on every code edit.
COPY composer.json composer.lock ./
RUN composer install \
        --no-scripts \
        --no-autoloader \
        --prefer-dist \
        --no-interaction

# ---- Application code ----
# In development this directory is overridden by the bind mount in docker-compose.yml;
# it is copied here too so the image is self-contained if run standalone.
COPY . .

RUN composer dump-autoload --optimize \
    && mkdir -p storage/app/public\storage framework/{cache,sessions,views} storage/logs bootstrap/cache \
    && chown -R laravel:laravel /var/www/html \
    && chmod -R 775 storage bootstrap/cache

COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

USER laravel

EXPOSE 9000

ENTRYPOINT ["entrypoint.sh"]
CMD ["php-fpm"]

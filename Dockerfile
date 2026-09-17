# ==============================================================================
# Stage 1: Build Frontend Assets (Vite / Tailwind)
# ==============================================================================
FROM node:20-alpine AS frontend
WORKDIR /app

COPY package*.json ./
RUN npm ci

COPY resources/ ./resources/
COPY vite.config.js ./
RUN npm run build

# ==============================================================================
# Stage 2: Install Composer Dependencies (Production)
# ==============================================================================
FROM composer:2 AS vendor
WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --prefer-dist \
    --optimize-autoloader \
    --no-scripts

# ==============================================================================
# Stage 3: Production Runtime (PHP 8.3 FPM + Nginx + Alpine)
# ==============================================================================
FROM php:8.3-fpm-alpine AS runtime

# Install system utilities, runtime libraries, and web server
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    netcat-openbsd \
    libpng \
    libjpeg-turbo \
    freetype \
    libzip \
    icu-libs

# Install build dependencies, compile PHP extensions, and clean up
RUN apk add --no-cache --virtual .build-deps \
    $PHPIZE_DEPS \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    libzip-dev \
    icu-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        pdo_mysql \
        gd \
        zip \
        bcmath \
        opcache \
        pcntl \
        exif \
        intl \
    && apk del --no-cache .build-deps

# Copy Composer binary for artisan / runtime usage if needed
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Copy web server & PHP configurations
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/opcache.ini /usr/local/etc/php/conf.d/opcache.ini
COPY docker/php/docker-vars.conf /usr/local/etc/php-fpm.d/docker-vars.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh

RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# Copy application source code
COPY . .

# Copy built vendor and frontend build assets from previous stages
COPY --from=vendor /app/vendor ./vendor
COPY --from=frontend /app/public/build ./public/build

# Finish composer autoloader generation
RUN composer dump-autoload --optimize --classmap-authoritative --no-dev

# Setup proper permissions for web user and Nginx temp/log directories
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache \
    && mkdir -p /var/lib/nginx/tmp/client_body \
                /var/lib/nginx/tmp/proxy \
                /var/lib/nginx/tmp/fastcgi \
                /var/log/nginx \
                /run/nginx \
    && chown -R www-data:www-data /var/lib/nginx /var/log/nginx /run/nginx \
    && chmod -R 775 /var/lib/nginx /var/log/nginx /run/nginx

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-n", "-c", "/etc/supervisord.conf"]

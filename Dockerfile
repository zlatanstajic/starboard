# Stage 1: Build frontend assets
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

# Stage 2: Install PHP dependencies
# Pinned to a composer image whose own PHP matches the app's "php": "^8.5"
# requirement, so the autoloader is generated on the runtime's PHP version.
FROM composer:2.10 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
# --ignore-platform-reqs as above: ext-pdo_mysql is installed in the runtime
# stage, not here. Stage 3 verifies the real platform with check-platform-reqs.
RUN composer dump-autoload --optimize --ignore-platform-reqs

# Stage 3: Production image
FROM php:8.5-fpm-alpine AS production

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    libjpeg-turbo-dev \
    libxml2-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip

# Install PHP extensions.
# dom, mbstring, pdo and Zend OPcache are already built into
# php:8.5-fpm-alpine, so they are not listed. Recompiling dom fails on PHP 8.5,
# whose ext/dom needs the lexbor headers the Alpine image does not ship, and
# installing a built-in extension leaves no module for make to copy. Stage 3's
# check-platform-reqs still proves every ext-* in composer.json is satisfied,
# and docker/php/php.ini tunes the OPcache that ships with the image.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        pdo_mysql \
        pcntl \
        zip

# Copy application from previous stages
COPY --from=composer-builder /app ./
COPY --from=node-builder /app/public/build ./public/build

# Verify that the runtime image satisfies the application's PHP requirements
COPY --from=composer-builder /usr/bin/composer /usr/local/bin/composer
RUN composer check-platform-reqs --no-dev \
    && rm /usr/local/bin/composer

# Copy custom PHP configuration
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# Set correct permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html/storage \
    && chmod -R 755 /var/www/html/bootstrap/cache

# Copy and set entrypoint
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 9000

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

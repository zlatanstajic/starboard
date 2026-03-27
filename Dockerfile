# Stage 1: Build frontend assets
FROM node:22-alpine AS node-builder

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --ignore-scripts

COPY . .
RUN npm run build

# Stage 2: Install PHP dependencies
FROM composer:2.8 AS composer-builder

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-scripts \
    --no-autoloader \
    --prefer-dist \
    --ignore-platform-reqs

COPY . .
RUN composer dump-autoload --optimize

# Stage 3: Production image
FROM php:8.3-fpm-alpine AS production

WORKDIR /var/www/html

# Install system dependencies
RUN apk add --no-cache \
    bash \
    curl \
    freetype-dev \
    libjpeg-turbo-dev \
    libpng-dev \
    libzip-dev \
    oniguruma-dev \
    unzip \
    zip

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        mbstring \
        pdo \
        pdo_mysql \
        pcntl \
        zip \
        opcache

# Copy application from previous stages
COPY --from=composer-builder /app ./
COPY --from=node-builder /app/public/build ./public/build

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

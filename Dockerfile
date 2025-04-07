# === STAGE 1: Composer install ===
FROM composer:2 AS composer

WORKDIR /app

COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --prefer-dist


# === STAGE 2: Production Apache/PHP container ===
FROM php:8.3-apache

# Install PHP extensions and system tools
RUN apt-get update && apt-get install -y unzip git && \
    docker-php-ext-install -j"$(nproc)" opcache

# PHP Cloud Run optimizations
RUN set -ex; \
  { \
    echo "memory_limit = -1"; \
    echo "max_execution_time = 0"; \
    echo "upload_max_filesize = 32M"; \
    echo "post_max_size = 32M"; \
    echo "opcache.enable = On"; \
    echo "opcache.validate_timestamps = Off"; \
    echo "opcache.memory_consumption = 32"; \
  } > "$PHP_INI_DIR/conf.d/cloud-run.ini"

# Set working directory
WORKDIR /var/www/html

# Copy app code
COPY . .

# Copy Composer-installed vendor files from the build stage
COPY --from=composer /app/vendor ./vendor

# Update Apache to use Cloud Run port
RUN sed -i 's/80/${PORT}/g' /etc/apache2/sites-available/000-default.conf /etc/apache2/ports.conf

# Use development config for PHP (you can switch to production if needed)
RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

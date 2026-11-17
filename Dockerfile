FROM php:8.3-fpm-alpine

# Install system dependencies
RUN apk add --no-cache \
    nginx \
    supervisor \
    curl \
    bash \
    git \
    unzip \
    libzip \
    libpng \
    libjpeg-turbo \
    freetype \
    icu-data-full \
    tzdata \
    netcat-openbsd

# Install PHP extensions using docker-php-extension-installer
COPY --from=mlocati/php-extension-installer:latest /usr/bin/install-php-extensions /usr/local/bin/

RUN install-php-extensions \
    pdo_mysql \
    mysqli \
    mbstring \
    bcmath \
    imap \
    xml \
    zip \
    fileinfo \
    ctype \
    json \
    tokenizer \
    openssl \
    iconv \
    gd \
    opcache \
    curl

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html

# Clean up any residual zips or temporary files
RUN rm -rf /var/www/html/*.zip /var/www/html/Copyright.txt 2>/dev/null || true

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/conf.d/custom.ini
COPY docker/php/fpm-pool.conf /usr/local/etc/php-fpm.d/www.conf
COPY docker/nginx/nginx.conf /etc/nginx/nginx.conf
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisord.conf
COPY docker/cron/crontab /etc/crontabs/root

# Setup entrypoint
COPY docker/docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# Ensure storage and bootstrap directories exist with proper permissions
RUN mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/run/nginx \
    /var/log/supervisor \
    && chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisord.conf"]

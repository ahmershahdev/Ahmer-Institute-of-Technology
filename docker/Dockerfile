# syntax=docker/dockerfile:1.7
FROM php:8.5-apache-bookworm AS base

ENV OPCACHE_VALIDATE=0
RUN set -eux; \
    apt-get update; \
    apt-get install -y --no-install-recommends \
        libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev \
        ghostscript ca-certificates default-mysql-client; \
    docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp; \
    docker-php-ext-install -j"$(nproc)" gd pdo_mysql mysqli; \
    php -m | grep -qi "zend opcache" || docker-php-ext-install opcache; \
    pecl install redis-6.3.0 apcu-5.1.28; \
    docker-php-ext-enable redis apcu; \
    a2enmod rewrite headers expires deflate brotli remoteip; \
    apt-get purge -y --auto-remove libfreetype6-dev libjpeg62-turbo-dev libpng-dev libwebp-dev; \
    rm -rf /var/lib/apt/lists/* /tmp/pear

COPY docker/php/ait.ini /usr/local/etc/php/conf.d/zz-ait.ini
COPY docker/apache/ait.conf /etc/apache2/sites-available/000-default.conf
COPY docker/apache/security.conf /etc/apache2/conf-available/ait-security.conf
RUN a2enconf ait-security \
 && echo '# Compression is configured in .htaccess (Brotli first, gzip fallback).' > /etc/apache2/mods-available/deflate.conf \
 && mkdir -p /var/lib/ait/uploads /var/lib/ait/storage \
 && chown -R www-data:www-data /var/lib/ait

WORKDIR /var/www/html/AIT
COPY --chown=www-data:www-data . /var/www/html/AIT
RUN rm -f .env && mkdir -p backend/storage && chown -R www-data:www-data backend/storage

HEALTHCHECK --interval=15s --timeout=5s --retries=10 CMD curl -fsS http://127.0.0.1/AIT/healthz.php || exit 1
EXPOSE 80

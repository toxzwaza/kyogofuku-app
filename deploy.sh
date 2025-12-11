FROM php:8.2-apache

# PHP ini 設定（あなたの現在の設定をそのまま反映）
RUN echo "upload_max_filesize = 0" >> /usr/local/etc/php/php.ini && \
    echo "post_max_size = 0" >> /usr/local/etc/php/php.ini && \
    echo "memory_limit = -1" >> /usr/local/etc/php/php.ini && \
    echo "max_execution_time = 300" >> /usr/local/etc/php/php.ini

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri "s!/var/www/html!${APACHE_DOCUMENT_ROOT}!g" \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/apache2.conf

RUN a2enmod rewrite

# PHP 拡張（現行維持）
RUN apt-get update && apt-get install -y \
    git curl zip unzip \
    libonig-dev libzip-dev libpng-dev libicu-dev \
    && docker-php-ext-install pdo_mysql mbstring zip gd intl

# Node と Composer（現行維持）
RUN curl -fsSL https://deb.nodesource.com/setup_lts.x | bash - \
    && apt-get install -y nodejs
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/local/bin --filename=composer

# -----------------------
# Laravel ソースコードを COPY
# -----------------------
WORKDIR /var/www/html
COPY ./app /var/www/html

# Laravel セットアップ（コンテナ内で完結）
RUN composer install --no-dev --optimize-autoloader \
    && npm ci \
    && npm run build \
    && php artisan storage:link || true \
    && php artisan key:generate --force || true \
    && php artisan config:clear \
    && php artisan route:clear

# パーミッション
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

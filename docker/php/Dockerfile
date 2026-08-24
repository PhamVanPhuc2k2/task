# PHP 8.4 — bắt buộc, không phải 8.3.
# Laravel 13 chấp nhận 8.3, nhưng spatie/laravel-model-states, spatie/laravel-activitylog
# và pestphp/pest v5 đều yêu cầu ^8.4. Xem README mục "Lưu ý về các package".
FROM php:8.4-fpm-alpine

# ── Thư viện hệ thống ────────────────────────────────
RUN apk add --no-cache \
        git \
        curl \
        bash \
        zip \
        unzip \
        icu-dev \
        libzip-dev \
        oniguruma-dev \
        libpng-dev \
        libjpeg-turbo-dev \
        freetype-dev \
        mysql-client \
        supervisor \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers

# ── Extension PHP ────────────────────────────────────
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        bcmath \
        intl \
        zip \
        gd \
        exif \
        pcntl \
        opcache

# Redis cho queue, cache, session
RUN pecl install redis \
    && docker-php-ext-enable redis \
    && pecl clear-cache

# Gỡ công cụ build để image gọn lại
RUN apk del .build-deps

# ── Composer ─────────────────────────────────────────
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# ── Cấu hình PHP ─────────────────────────────────────
COPY php.ini /usr/local/etc/php/conf.d/99-app.ini

# Entrypoint nới quyền ghi cho storage/ và bootstrap/cache mỗi lần khởi động.
# Xem chú thích trong file để biết vì sao cần.
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

WORKDIR /var/www/html

# php-fpm chạy dưới www-data (mặc định của image). Vấn đề quyền ghi trên bind
# mount do entrypoint xử lý, không tạo thêm user riêng.
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]

FROM php:8.3-fpm-alpine

RUN apk add --no-cache \
    bash \
    curl \
    icu-dev \
    libzip-dev \
    mysql-client \
    oniguruma-dev \
    sqlite-dev \
    $PHPIZE_DEPS \
  && docker-php-ext-install \
    bcmath \
    intl \
    mbstring \
    pcntl \
    pdo_mysql \
    pdo_sqlite \
    zip \
  && pecl install redis \
  && docker-php-ext-enable redis \
  && apk del $PHPIZE_DEPS

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
COPY docker/php/entrypoint.sh /usr/local/bin/amar-entrypoint

WORKDIR /var/www/html

RUN chmod +x /usr/local/bin/amar-entrypoint

ENTRYPOINT ["amar-entrypoint"]
CMD ["php-fpm"]

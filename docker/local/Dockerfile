FROM php:8.4-fpm-alpine3.21

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

WORKDIR /var/www/html

RUN apk add --no-cache \
    curl git unzip nginx supervisor nodejs npm nano

RUN apk add --no-cache mysql-client --repository=http://alpinelinux.org

RUN install-php-extensions gd pdo_mysql opcache redis

RUN ln -sf /usr/bin/php84 /usr/bin/php

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Reuse staging nginx + supervisor configs
COPY ./docker/staging/nginx.conf /etc/nginx/http.d/default.conf
COPY ./docker/staging/supervisord.conf /etc/supervisor/conf.d/supervisord.conf
COPY ./docker/staging/php.ini /usr/local/etc/php/conf.d/custom.ini

RUN mkdir -p /var/www/html && chown -R www-data:www-data /var/www/html

COPY . /var/www/html

RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache \
    && chmod -R a+rw /var/www/html/storage /var/www/html/bootstrap/cache

RUN git config --global --add safe.directory /var/www/html

RUN composer install --no-interaction --prefer-dist --optimize-autoloader

RUN npm ci && npm run build

COPY ./docker/local/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

RUN sed -i 's|;chdir = /var/www|chdir = /var/www/html|g' /usr/local/etc/php-fpm.d/www.conf

EXPOSE 80

ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

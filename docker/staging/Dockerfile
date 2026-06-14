FROM php:8.4-fpm-alpine3.21

COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/

# Set working directory
WORKDIR /var/www/html

# Install core framework utilities, Nginx, Supervisor, Node, and NPM
RUN apk add --no-cache \
    curl \
    git \
    unzip \
    nginx \
    supervisor \
    nodejs \
    npm \
    nano



RUN apk add --no-cache mysql-client --repository=http://alpinelinux.org


COPY --from=mlocati/php-extension-installer /usr/bin/install-php-extensions /usr/local/bin/
RUN install-php-extensions gd pdo_mysql opcache redis

# Map global PHP binaries straight to the new 8.4 instance
RUN ln -sf /usr/bin/php84 /usr/bin/php

# Get latest Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy configuration files into the container
COPY ./docker/staging/nginx.conf /etc/nginx/http.d/default.conf
COPY ./docker/staging/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

RUN mkdir -p /var/www/html
RUN chown -R www-data:www-data /var/www/html

# Copy application files
COPY . /var/www/html

# Fix folder permissions for Alpine's default web user (nobody)
 RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
 RUN chmod -R a+rw /var/www/html/storage /var/www/html/bootstrap/cache

# Copy and prepare entrypoint automation script
COPY ./docker/staging/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh


RUN sed -i 's|;chdir = /var/www|chdir = /var/www/html|g' /usr/local/etc/php-fpm.d/www.conf



# Expose HTTP port
EXPOSE 80

# Call the automation entrypoint script
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]

FROM composer:2@sha256:af98f42dfff7c68ba8d53c2164fd9fde1087b7d449514baa38c418b1f6bc4bac AS composer
FROM php:8.4-apache@sha256:901b0dbcd2419cc9cd307ea05e57403449ce722ccd5b32335da6e8d39f2b1ee0
RUN apt-get update \
    && apt-get install -y --no-install-recommends libcurl4-openssl-dev unzip \
    && docker-php-ext-install curl pdo_mysql \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer /usr/bin/composer /usr/local/bin/composer
WORKDIR /app
COPY composer.json composer.lock ./
RUN composer install --no-dev --no-interaction --no-progress --prefer-dist --optimize-autoloader
COPY . /app
RUN printf 'display_errors=Off\nlog_errors=On\nexpose_php=Off\n' > /usr/local/etc/php/conf.d/production.ini \
    && sed -i 's#/var/www/html#/app/public#g' /etc/apache2/sites-available/000-default.conf \
    && printf '<Directory /app/public>\nRequire all granted\nOptions -Indexes\nAllowOverride None\n</Directory>\n' > /etc/apache2/conf-available/bot.conf \
    && a2enconf bot
EXPOSE 80
CMD ["apache2-foreground"]

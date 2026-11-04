FROM php:8.3-apache

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite headers \
    && sed -ri 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

WORKDIR /var/www/html
COPY . /var/www/html/
COPY docker/entrypoint.sh /usr/local/bin/iskonnect-entrypoint
COPY docker/php.ini /usr/local/etc/php/conf.d/iskonnect.ini

RUN chmod +x /usr/local/bin/iskonnect-entrypoint \
    && mkdir -p /data/profiles /data/storage/organization-files /data/storage/organization-officers \
    && chown -R www-data:www-data /data

EXPOSE 8080
CMD ["/usr/local/bin/iskonnect-entrypoint"]

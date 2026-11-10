# PHP 8.3 + Apache, jak na docelowym serwerze.
FROM php:8.3-apache

# Rozszerzenie do łączenia się z MySQL przez PDO.
RUN docker-php-ext-install pdo_mysql

# Tylko katalog public/ jest widoczny z przeglądarki (src/, config/, database/ nie).
ENV APACHE_DOCUMENT_ROOT=/var/www/acai-cup/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
 && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
 && a2enmod headers rewrite

WORKDIR /var/www/acai-cup

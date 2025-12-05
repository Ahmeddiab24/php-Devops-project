FROM php:8.1-apache

RUN docker-php-ext-install mysqli && docker-php-ext-enable mysqli

RUN docker-php-ext-install pdo pdo_mysql

RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html/images /var/www/html/logs \
    && chmod -R 755 /var/www/html/images /var/www/html/logs

EXPOSE 80

CMD ["apache2-foreground"]
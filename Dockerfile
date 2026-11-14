FROM php:8.3-apache

# Extensão do PDO para MariaDB/MySQL + reescrita de URLs
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers

COPY docker/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/entrypoint.sh /usr/local/bin/fatecpoll-entrypoint
RUN chmod +x /usr/local/bin/fatecpoll-entrypoint

WORKDIR /var/www/html
COPY . /var/www/html

ENTRYPOINT ["fatecpoll-entrypoint"]
CMD ["apache2-foreground"]

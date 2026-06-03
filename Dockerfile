FROM php:8.3-apache
RUN a2enmod rewrite
COPY 000-default.conf /etc/apache2/sites-available/000-default.conf
COPY index.php /var/www/html/index.php
EXPOSE 80

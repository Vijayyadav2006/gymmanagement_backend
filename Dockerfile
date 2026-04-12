FROM php:8.2-apache

# Install MySQLi extension
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Expose Render port
EXPOSE 10000

# Apache must listen on 10000 (Render requirement)
RUN sed -i 's/80/10000/g' /etc/apache2/ports.conf \
    && sed -i 's/:80/:10000/g' /etc/apache2/sites-available/000-default.conf

CMD ["apache2-foreground"]
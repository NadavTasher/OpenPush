# Select the base image
FROM php:7.4.1-apache
# Enable modifications
RUN a2enmod headers
# Copy sources to /var/www/html
COPY src /var/www/html
# Change ownership & permissions of /var/www
RUN chown www-data /var/www/ -R && chmod 775 /var/www/ -R
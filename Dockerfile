FROM php:8.2-apache

# Installer les extensions PHP nécessaires
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Activer mod_rewrite pour Apache
RUN a2enmod rewrite

# Copier les fichiers de l'application
COPY . /var/www/html/

# Définir les permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Exposer le port 80
EXPOSE 80

# Démarrer Apache
CMD ["apache2-foreground"]

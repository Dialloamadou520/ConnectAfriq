FROM php:8.2-apache

# Installer les dépendances pour PostgreSQL
RUN apt-get update && apt-get install -y libpq-dev

# Installer les extensions PHP nécessaires (MySQL + PostgreSQL)
RUN docker-php-ext-install pdo pdo_mysql mysqli pdo_pgsql pgsql

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

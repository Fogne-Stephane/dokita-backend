# Utiliser l'image officielle PHP 8.4 avec Apache
FROM php:8.4-apache

# Installer les dépendances système et extensions PHP requises par Laravel
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    git \
    curl \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql pdo_mysql mbstring exif pcntl bcmath gd

# Activer la réécriture d'URL sur Apache
RUN a2enmod rewrite

# Installer Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Définir le dossier de travail
WORKDIR /var/www/html

# Copier le code du projet
COPY . .

# Installer les dépendances Laravel sans les packages de dev
RUN composer install --no-dev --optimize-autoloader

# Rediriger le pointeur d'Apache vers le dossier /public de Laravel
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/conf-available/*.conf

# Configurer les permissions pour Laravel
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 80

CMD ["apache2-foreground"]
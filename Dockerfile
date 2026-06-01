FROM php:8.2-apache

# Instalamos las extensiones de MySQL para PHP
RUN docker-php-ext-install pdo pdo_mysql

# Habilitamos el mod_rewrite de Apache para las rutas de la API
RUN a2enmod rewrite

WORKDIR /var/www/html
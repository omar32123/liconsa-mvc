FROM php:8.2-apache

# Instalamos dependencias del sistema para el cliente de Firebird
RUN apt-get update && apt-get install -y \
    libfbclient2 \
    firebird-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalamos la extensión PDO para Firebird
RUN docker-php-ext-install pdo_firebird

# Activamos rewrite para las rutas de tu app
RUN a2enmod rewrite

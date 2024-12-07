FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip

# Clear cache
RUN apt-get clean && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Enable Apache modules
RUN a2enmod rewrite headers

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files first
COPY composer.json composer.lock ./

# Install composer dependencies
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application files
COPY . .

# Create public directory and move index.php
RUN mkdir -p public \
    && echo "<?php phpinfo(); ?>" > public/index.php \
    && echo "<?php echo 'healthy'; ?>" > public/health.php

# Set Apache document root to public
ENV APACHE_DOCUMENT_ROOT /var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Create storage directory and set permissions
RUN mkdir -p storage/logs storage/cache \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Configure Apache for Railway
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf \
    && echo "Listen \${PORT}" >> /etc/apache2/ports.conf \
    && sed -i 's/Listen 80/Listen \${PORT}/g' /etc/apache2/sites-available/000-default.conf

# Create start script
RUN echo '#!/bin/bash\n\
sed -i "s/\${PORT}/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf\n\
apache2-foreground' > /usr/local/bin/start-apache \
    && chmod +x /usr/local/bin/start-apache

# Expose port
EXPOSE 80

# Start Apache with dynamic port
CMD ["/usr/local/bin/start-apache"]

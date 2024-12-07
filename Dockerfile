FROM php:8.1-cli

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

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-scripts --no-autoloader --no-dev

# Copy application files
COPY . .

# Create storage directory and set permissions
RUN mkdir -p storage/logs storage/cache \
    && chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && find /var/www/html -type d -exec chmod 755 {} \;

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Create start script
COPY docker/start.sh /usr/local/bin/start.sh
RUN chmod +x /usr/local/bin/start.sh

# Set environment variables
ENV JWT_SECRET_KEY="+WQFBKMY3oH7qSqi0Vx+3kW5RA8PI/zCCTOCw8NFaELLVKNvtuxqVPadVTm5JqQIPjGbNT9FU1YT7juByrFSdg=="
ENV SUPABASE_URL="https://kgqwiwjayaydewyuygxt.supabase.co"
ENV SUPABASE_SERVICE_ROLE_KEY="eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJpc3MiOiJzdXBhYmFzZSIsInJlZiI6ImtncXdpd2pheWF5ZGV3eXV5Z3h0Iiwicm9sZSI6InNlcnZpY2Vfcm9sZSIsImlhdCI6MTczMzI0MjQxNiwiZXhwIjoyMDQ4ODE4NDE2fQ.icrGci0zm7HppVhF5BNnXZiBwLgtj2s8am2cHOdwtho"

# Expose port
ENV PORT=8080

# Start script as entrypoint
ENTRYPOINT ["/usr/local/bin/start.sh"]

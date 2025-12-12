# Use PHP CLI image
FROM php:8.1-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libzip-dev unzip git curl

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin --filename=composer

# Set working directory
WORKDIR /var/www

# Copy project files
COPY . .

# Install PHP dependencies
RUN composer install --optimize-autoloader --no-dev

# Expose port 8000 for Laravel
EXPOSE 8000

# Start Laravel server
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]

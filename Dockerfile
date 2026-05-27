FROM php:8.3-cli

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    unzip \
    git \
    && docker-php-ext-install pdo_sqlite \
    && rm -rf /var/lib/apt/lists/*

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /app

# Copy composer files first for better caching
COPY composer.json ./
RUN composer install --prefer-dist --no-progress --no-scripts --no-autoloader

# Copy application code
COPY . .

# Regenerate autoloader with full class map
RUN composer dump-autoload --optimize

# Create data directory
RUN mkdir -p data && chmod 777 data

# Expose API port
EXPOSE 8080

# Default command: start the API server
CMD ["php", "-S", "0.0.0.0:8080", "api.php"]

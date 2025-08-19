# ARMIS Docker Configuration
# Production-ready containerization for scalable deployment

FROM php:8.1-apache

# Install system dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libonig-dev \
    libxml2-dev \
    redis-server \
    supervisor \
    cron \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath zip

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Configure Apache
RUN a2enmod rewrite ssl headers
COPY docker/apache/armis.conf /etc/apache2/sites-available/
RUN a2dissite 000-default && a2ensite armis

# Set working directory
WORKDIR /var/www/html

# Copy application code
COPY . /var/www/html/

# Create necessary directories
RUN mkdir -p /var/www/html/shared/logs \
    /var/www/html/shared/keys \
    /var/www/html/cache \
    /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod -R 777 /var/www/html/shared/logs \
    && chmod -R 700 /var/www/html/shared/keys \
    && chmod -R 777 /var/www/html/cache \
    && chmod -R 777 /var/www/html/uploads

# Copy configuration files
COPY docker/php/php.ini /usr/local/etc/php/
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/
COPY docker/cron/armis-cron /etc/cron.d/

# Set cron permissions
RUN chmod 0644 /etc/cron.d/armis-cron

# PHP configuration for production
RUN echo "memory_limit = 512M" >> /usr/local/etc/php/conf.d/docker-php-memlimit.ini \
    && echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/docker-php-uploads.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/docker-php-uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/docker-php-timeouts.ini \
    && echo "session.cookie_secure = 1" >> /usr/local/etc/php/conf.d/docker-php-security.ini \
    && echo "session.cookie_httponly = 1" >> /usr/local/etc/php/conf.d/docker-php-security.ini

# Expose ports
EXPOSE 80 443

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD curl -f http://localhost/api/v1/health || exit 1

# Start services
CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]
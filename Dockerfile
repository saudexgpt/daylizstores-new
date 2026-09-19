# Set the base image for subsequent instructions
FROM php:8.2-cli

WORKDIR /var/www

# Update packages

RUN curl -sL https://deb.nodesource.com/setup_18.x | bash - \
    && apt-get update \
    && apt-get install -y nodejs netcat-openbsd libjpeg-dev libpng-dev libfreetype6-dev libbz2-dev libzip-dev git \
    && apt-get clean

# Install extensions
RUN docker-php-ext-install pdo_mysql mbstring zip exif pcntl
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install gd

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

COPY . .
COPY .env.example .env

CMD ["bash", "./laravue-entrypoint.sh"]


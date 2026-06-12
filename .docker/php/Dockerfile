FROM php:8.5.5-fpm-bookworm

# Install Composer
COPY --from=composer:2.9.7 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
  && apt-get install -y \
  bash \
  coreutils \
  findutils \
  gawk \
  git \
  grep \
  gzip \
  libfreetype6-dev \
  libicu-dev \
  libjpeg-dev \
  libonig-dev \
  libpng-dev \
  libwebp-dev \
  libzip-dev \
  mariadb-client \
  unzip \
  # Configure GD with JPEG/WebP/FreeType support
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  # Install PCOV (fast code-coverage driver)
  && pecl install pcov \
  && docker-php-ext-enable pcov \
  # Install PHP Extension
  && docker-php-ext-install \
  bcmath \
  exif \
  gd \
  intl \
  mbstring \
  pdo_mysql \
  zip \
  # Install Laravel
  && composer global require "laravel/installer" \
  # Clean up
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

ENV PATH=/root/.composer/vendor/bin:$PATH
WORKDIR /app

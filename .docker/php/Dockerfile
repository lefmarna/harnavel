FROM php:8.5.5-fpm-bookworm

# Install Composer
COPY --from=composer:2.9.7 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
  && apt-get install -y curl \
  && curl -fsSL https://deb.nodesource.com/setup_24.x | bash - \
  && apt-get install -y \
  bash \
  coreutils \
  findutils \
  gawk \
  git \
  grep \
  gzip \
  libicu-dev \
  libonig-dev \
  libpng-dev \
  libzip-dev \
  mariadb-client \
  nodejs \
  unzip \
  # Install Xdebug
  && pecl install xdebug \
  && docker-php-ext-enable xdebug \
  # Install PHP Extension
  && docker-php-ext-install \
  bcmath \
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

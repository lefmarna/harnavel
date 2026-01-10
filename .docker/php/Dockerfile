FROM php:8.4.12-fpm-bookworm

# Install Composer
COPY --from=composer:2.9.3 /usr/bin/composer /usr/bin/composer

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
  # Install Node.js 24.12.0
  nodejs=24.12.0-1nodesource1 \
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
  opcache \
  pdo_mysql \
  zip \
  # Install Laravel
  && composer global require "laravel/installer" \
  # Clean up
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

ENV PATH=~/.composer/vendor/bin:$PATH
WORKDIR /app

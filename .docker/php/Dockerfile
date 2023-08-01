FROM php:8.2.5-fpm-buster

# Install Composer
COPY --from=composer:2.5.8 /usr/bin/composer /usr/bin/composer

RUN apt-get update \
  # 最新のnodejs(安定版)をインストールできるようにする
  && curl -sL https://deb.nodesource.com/setup_18.x| bash - \
  && apt-get install -y \
  git \
  nodejs \
  libicu-dev \
  libonig-dev \
  libpng-dev \
  libzip-dev \
  unzip \
  && git clone https://github.com/phpredis/phpredis.git /usr/src/php/ext/redis \
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
  redis \
  zip \
  # Install Laravel
  && composer global require "laravel/installer"
ENV PATH ~/.composer/vendor/bin:$PATH
WORKDIR /app

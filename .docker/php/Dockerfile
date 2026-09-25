FROM php:8.5.9-fpm-bookworm AS app

COPY --from=composer:2.10.2 /usr/bin/composer /usr/bin/composer

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
  && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
  && pecl install pcov \
  && docker-php-ext-enable pcov \
  && docker-php-ext-install \
  bcmath \
  exif \
  gd \
  intl \
  mbstring \
  pdo_mysql \
  zip \
  && composer global require "laravel/installer" \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

ENV PATH=/root/.composer/vendor/bin:$PATH
WORKDIR /app

# 参照アプリ生成（update-laravel-packages スキル）専用の使い捨てステージ。
FROM app AS package-reference

RUN apt-get update \
  && curl -fsSL https://deb.nodesource.com/setup_24.x -o /tmp/nodesource_setup.sh \
  && bash /tmp/nodesource_setup.sh \
  && rm /tmp/nodesource_setup.sh \
  && apt-get install -y nodejs \
  && corepack enable \
  && apt-get clean \
  && rm -rf /var/lib/apt/lists/*

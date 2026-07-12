#!/bin/sh
# king-shop PHP 基础扩展安装脚本
set -e

apt-get update
apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libwebp-dev \
    libonig-dev \
    libxml2-dev

docker-php-ext-configure gd --enable-gd --with-freetype --with-jpeg --with-webp

docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    zip \
    mbstring \
    xml \
    bcmath \
    pcntl \
    gd

# redis：从 GitHub 源码编译（避免 pecl 网络问题）
curl -fsSL https://github.com/phpredis/phpredis/archive/refs/tags/6.1.0.tar.gz -o /tmp/redis.tar.gz
tar -xzf /tmp/redis.tar.gz -C /tmp
cd /tmp/phpredis-6.1.0
phpize
./configure
make -j"$(nproc)"
make install
docker-php-ext-enable redis
cd /
rm -rf /tmp/redis.tar.gz /tmp/phpredis-6.1.0

apt-get clean
rm -rf /var/lib/apt/lists/*

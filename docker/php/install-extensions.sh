#!/bin/sh
# king-shop PHP 基础扩展安装脚本
# 扩展此文件即可为所有后端镜像添加新扩展
set -e

apt-get update
apt-get install -y --no-install-recommends \
    git \
    unzip \
    libzip-dev \
    libpng-dev \
    libonig-dev \
    libxml2-dev

docker-php-ext-install -j"$(nproc)" \
    pdo_mysql \
    zip \
    mbstring \
    xml \
    bcmath \
    pcntl

pecl install redis-6.1.0
docker-php-ext-enable redis

apt-get clean
rm -rf /var/lib/apt/lists/*

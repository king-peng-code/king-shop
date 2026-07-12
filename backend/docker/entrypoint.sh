#!/bin/sh
set -e

cd /var/www/html

# 确保 Laravel 可写目录
chmod -R 775 storage bootstrap/cache 2>/dev/null || true

# 首次启动：生成 .env
if [ ! -f .env ]; then
    cp .env.example .env
    php artisan key:generate --force
fi

# 安装依赖
if [ ! -d vendor ]; then
    composer install --no-interaction --prefer-dist
fi

# 等待 MySQL 就绪后迁移
echo "Waiting for database..."
until php -r "
    try {
        new PDO(
            'mysql:host=' . getenv('DB_HOST') . ';port=' . (getenv('DB_PORT') ?: '3306'),
            getenv('DB_USERNAME'),
            getenv('DB_PASSWORD')
        );
        exit(0);
    } catch (Exception \$e) {
        exit(1);
    }
" 2>/dev/null; do
    sleep 2
done

php artisan migrate --force
php artisan tinker --execute="app(\\App\\Infrastructure\\Cache\\CategoryListCache::class)->forget();" 2>/dev/null || true
php artisan config:cache
php artisan route:cache
php artisan db:seed --class=SuperAdminSeeder --force
php artisan db:seed --class=SystemConfigSeeder --force
php artisan storage:link --force 2>/dev/null || true

echo "Laravel backend ready at http://localhost:8000"
exec php artisan serve --host=0.0.0.0 --port=8000 --no-reload

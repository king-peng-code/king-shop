#!/bin/sh
set -e

cd /var/www/html

# 等待 MySQL
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

# 生产环境 APP_KEY 必须由环境变量或 .env 提供
if [ -z "$APP_KEY" ] && [ ! -f .env ]; then
    echo "ERROR: APP_KEY must be set in production"
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan migrate --force

echo "Production backend ready (php-fpm)"
exec php-fpm

#!/bin/bash
set -e

echo "==> Initializing TMail container..."

# Ensure required directories exist
mkdir -p \
    /var/www/html/storage/app/public \
    /var/www/html/storage/framework/cache/data \
    /var/www/html/storage/framework/sessions \
    /var/www/html/storage/framework/views \
    /var/www/html/storage/logs \
    /var/www/html/bootstrap/cache \
    /var/run/nginx \
    /var/log/supervisor

# Fix permissions
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache

# Create .env if not exists
if [ ! -f /var/www/html/.env ]; then
    echo "==> Creating .env from .env.example..."
    cp /var/www/html/.env.example /var/www/html/.env
fi

# Function to update or add key in .env
set_env() {
    local key="$1"
    local value="$2"
    if [ -n "$value" ]; then
        if grep -q "^${key}=" /var/www/html/.env; then
            sed -i "s|^${key}=.*|${key}=\"${value}\"|" /var/www/html/.env
        else
            echo "${key}=\"${value}\"" >> /var/www/html/.env
        fi
    fi
}

# Update .env from container environment variables if provided
[ -n "$APP_NAME" ] && set_env "APP_NAME" "$APP_NAME"
[ -n "$APP_ENV" ] && set_env "APP_ENV" "$APP_ENV"
[ -n "$APP_DEBUG" ] && set_env "APP_DEBUG" "$APP_DEBUG"
[ -n "$APP_URL" ] && set_env "APP_URL" "$APP_URL"
[ -n "$DB_CONNECTION" ] && set_env "DB_CONNECTION" "$DB_CONNECTION"
[ -n "$DB_HOST" ] && set_env "DB_HOST" "$DB_HOST"
[ -n "$DB_PORT" ] && set_env "DB_PORT" "$DB_PORT"
[ -n "$DB_DATABASE" ] && set_env "DB_DATABASE" "$DB_DATABASE"
[ -n "$DB_USERNAME" ] && set_env "DB_USERNAME" "$DB_USERNAME"
[ -n "$DB_PASSWORD" ] && set_env "DB_PASSWORD" "$DB_PASSWORD"
[ -n "$SESSION_DRIVER" ] && set_env "SESSION_DRIVER" "$SESSION_DRIVER"

# Check if APP_KEY is empty or not generated
if ! grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    echo "==> Generating application key..."
    php artisan key:generate --force
fi

# Ensure APP_KEY is exported into the environment for supervisord and php-fpm
if grep -q "^APP_KEY=base64:" /var/www/html/.env; then
    export APP_KEY="$(grep '^APP_KEY=' /var/www/html/.env | head -n 1 | cut -d '=' -f2- | tr -d '\"' | tr -d '\r')"
fi

# Storage symlink
php artisan storage:link --force 2>/dev/null || true

# Auto-install routine
if [ "${AUTO_INSTALL:-false}" = "true" ] || [ "${AUTO_INSTALL:-false}" = "1" ]; then
    echo "==> Auto-install requested. Waiting for database connection..."
    php -r '
    $host = getenv("DB_HOST") ?: "db";
    $port = getenv("DB_PORT") ?: 3306;
    $db   = getenv("DB_DATABASE") ?: "tmail";
    $user = getenv("DB_USERNAME") ?: "tmail";
    $pass = getenv("DB_PASSWORD") ?: "tmail_password";
    $retries = 30;
    while ($retries > 0) {
        try {
            $pdo = new PDO("mysql:host=$host;port=$port;dbname=$db", $user, $pass);
            echo "==> Database connected successfully!\n";
            exit(0);
        } catch (PDOException $e) {
            echo "Waiting for database connection ($retries retries left)...\n";
            sleep(2);
            $retries--;
        }
    }
    echo "ERROR: Could not connect to database!\n";
    exit(1);
    '

    if [ ! -f /var/www/html/storage/installed ]; then
        echo "==> Running migrations..."
        php artisan migrate:fresh --force
        echo "==> Running seeders..."
        php artisan db:seed --force
        echo "==> Running automated TMail setup..."
        php /var/www/html/docker/auto-setup.php
    else
        echo "==> TMail is already marked as installed."
    fi
fi

# Clear view & config cache for clean startup
php artisan view:clear 2>/dev/null || true
php artisan config:clear 2>/dev/null || true

echo "==> Starting supervisor services..."
exec "$@"

#!/bin/bash
set -e

# Create .env from example if missing, then override with Docker env vars
if [ ! -f .env ]; then
    cp .env.example .env
    echo "Created .env from .env.example"
fi

# Generate APP_KEY if not provided via environment
if [ -z "$APP_KEY" ] && ! grep -q '^APP_KEY=base64:' .env 2>/dev/null; then
    php artisan key:generate --force
    echo "Generated APP_KEY"
fi

# Override .env values from Docker environment variables
env_override() {
    local key="$1" val="$2"
    if [ -n "$val" ]; then
        if grep -q "^${key}=" .env; then
            sed -i "s|^${key}=.*|${key}=${val}|" .env
        else
            echo "${key}=${val}" >> .env
        fi
    fi
}

env_override "APP_ENV" "$APP_ENV"
env_override "APP_DEBUG" "$APP_DEBUG"
env_override "APP_URL" "$APP_URL"
env_override "DB_CONNECTION" "$DB_CONNECTION"
env_override "DB_HOST" "$DB_HOST"
env_override "DB_PORT" "$DB_PORT"
env_override "DB_DATABASE" "$DB_DATABASE"
env_override "DB_USERNAME" "$DB_USERNAME"
env_override "DB_PASSWORD" "$DB_PASSWORD"
env_override "OSINT_ENGINE_URL" "$OSINT_ENGINE_URL"
env_override "OSINT_ENGINE_SECRET" "$OSINT_ENGINE_SECRET"
env_override "QUEUE_CONNECTION" "$QUEUE_CONNECTION"
env_override "SESSION_DRIVER" "$SESSION_DRIVER"
env_override "CACHE_STORE" "$CACHE_STORE"

# Wait for MySQL
echo "Waiting for MySQL at ${DB_HOST:-mysql}:${DB_PORT:-3306}..."
until php -r "
    try { new PDO('mysql:host='.getenv('DB_HOST').';port='.getenv('DB_PORT'), getenv('DB_USERNAME'), getenv('DB_PASSWORD'));
          echo 'ok'; }
    catch (Exception \$e) { exit(1); }
" 2>/dev/null | grep -q 'ok'; do
    sleep 2
done
echo "MySQL is ready."

# Run migrations and seed only on the web container (not queue)
if [ "${RUN_MIGRATIONS}" = "true" ]; then
    echo "Running migrations..."
    php artisan migrate --force

    echo "Seeding templates..."
    php artisan db:seed --class=TemplateSeeder --force

    echo "Caching config..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache
fi

case "${1}" in
    serve)
        echo "Starting Laravel on 0.0.0.0:8000..."
        exec php artisan serve --host=0.0.0.0 --port=8000
        ;;
    queue)
        echo "Starting queue worker..."
        exec php artisan queue:work --tries=1 --verbose --timeout=900
        ;;
    *)
        exec "$@"
        ;;
esac

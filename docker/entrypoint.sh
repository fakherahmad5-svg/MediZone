#!/bin/sh
set -e

cd /var/www/html

# Install composer dependencies if the vendor directory is missing
# (first run, or a fresh clone with an empty named volume)
if [ ! -f "vendor/autoload.php" ]; then
    echo "[entrypoint] Installing composer dependencies..."
    composer install --no-interaction --prefer-dist --optimize-autoloader
fi

# Create .env from the docker template if it doesn't exist yet
if [ ! -f ".env" ]; then
    echo "[entrypoint] .env not found, copying .env.docker..."
    cp .env.docker .env
fi

# Generate an application key if one isn't set
if ! grep -q "^APP_KEY=base64" .env 2>/dev/null; then
    echo "[entrypoint] Generating application key..."
    php artisan key:generate --force
fi

# Wait for MySQL to accept connections before running migrations
echo "[entrypoint] Waiting for MySQL..."
until php artisan db:show > /dev/null 2>&1; do
    sleep 1
done
echo "[entrypoint] MySQL is up."

# Run migrations automatically for a smooth first-run experience.
# Comment this out if your team prefers running migrations manually.
php artisan migrate --seed --force

# Cache config/routes for a small perf boost (harmless in dev, skip if it gets in your way)
php artisan storage:link || true

exec "$@"

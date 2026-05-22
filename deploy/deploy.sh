#!/bin/bash
# Deployment script for automation-project
# Run as: bash deploy/deploy.sh
# Assumes: git pull already done, running from project root as www-data or sudo

set -e

APP_DIR="/var/www/automation-project"
PHP="php8.2"

echo "=== Starting deployment ==="

cd "$APP_DIR"

# 1. Pull latest code
echo "--- Pulling latest code ---"
git pull origin main

# 2. Install PHP dependencies (no dev, optimized)
echo "--- Installing Composer dependencies ---"
composer install --no-dev --optimize-autoloader --no-interaction

# 3. Install and build frontend assets
echo "--- Building frontend assets ---"
npm ci --no-audit
npm run build

# 4. Run migrations
echo "--- Running database migrations ---"
$PHP artisan migrate --force

# 5. Clear and rebuild caches
echo "--- Rebuilding caches ---"
$PHP artisan config:cache
$PHP artisan route:cache
$PHP artisan view:cache
$PHP artisan event:cache

# 6. Restart queue workers via Supervisor
echo "--- Restarting queue workers ---"
$PHP artisan queue:restart
sudo supervisorctl restart automation-workers:*

# 7. Fix storage permissions
echo "--- Fixing storage permissions ---"
chown -R www-data:www-data storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo "=== Deployment complete ==="

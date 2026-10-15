#!/usr/bin/env bash
# Deploy the latest main branch on the production server (AlmaLinux, nginx + PHP-FPM).
#
#   cd /var/www/rdu && ./scripts/deploy.sh
#
# Or in one command from the dev machine:
#   ssh -t nuttapong@192.168.1.244 /var/www/rdu/scripts/deploy.sh
#
# Stops at the first failing step:
#   - before the code changes → the site comes back up on the old code
#   - after the code changes  → the site stays in maintenance mode; fix the cause and run the
#                               script again — it finishes the remaining steps on the new code
# Never touches .env or storage/app/private/config/rdu_database.json (not in git).
set -euo pipefail

cd "$(dirname "$0")/.."

step() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }
fail() { printf '\n\033[1;31m✗ %s\033[0m\n' "$1" >&2; exit 1; }

# ---- Safety checks -------------------------------------------------------------------------
[ -f .env ] || fail ".env not found — this is not a configured install"
env_value() { grep -E "^$1=" .env | tail -1 | cut -d= -f2- | tr -d '"'; }

[ "$(env_value APP_ENV)" = "production" ] || fail "APP_ENV is not production — refusing to deploy"
[ "$(env_value DB_DATABASE)" = "rdu_dashboard" ] \
    || fail "DB_DATABASE is not rdu_dashboard — migrations must never run against HOSxP"

if [ -n "$(git status --porcelain --untracked-files=no)" ]; then
    git status --short --untracked-files=no
    fail "Files tracked by git were edited on the server — make the change on the dev machine instead"
fi

# ---- Fetch ---------------------------------------------------------------------------------
step "Fetching from GitHub"
before=$(git rev-parse --short HEAD)
git fetch --quiet origin main
after=$(git rev-parse --short origin/main)
resuming=false

if [ "$before" = "$after" ]; then
    if [ -f storage/framework/down ]; then
        resuming=true
        echo "Code is already at $after but the site is in maintenance mode — finishing the previous deploy."
    else
        echo "Already up to date ($before). Nothing to deploy."
        exit 0
    fi
else
    git --no-pager log --oneline "$before..$after"
fi

# ---- Deploy --------------------------------------------------------------------------------
if [ "$resuming" = false ]; then
    step "Maintenance mode on"
    php artisan down --retry=15
    trap 'php artisan up >/dev/null 2>&1; fail "Deploy failed before the code changed — site is back up on $before."' ERR

    step "Updating code ($before → $after)"
    git merge --ff-only --quiet origin/main
fi

# The code is new from here on; bringing the site up half-deployed could break it, so stay down.
trap 'fail "Deploy failed on $after — site is still in maintenance mode. Fix the error above and run ./scripts/deploy.sh again."' ERR

step "PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

step "Front-end build"
npm ci --no-audit --no-fund
npm run build

step "Database migrations (rdu_dashboard only)"
php artisan migrate --force

# KPI names, numerator/denominator text and targets live in the seeder, not in a migration.
# Only RduIndicatorSeeder — the full DatabaseSeeder creates an admin/admin user.
step "KPI definitions (RduIndicatorSeeder — update in place, deletes nothing)"
php artisan db:seed --class=RduIndicatorSeeder --force

step "Caches"
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan cache:clear   # cached KPI results were graded against the old code and targets

step "Reloading PHP-FPM (OPcache does not watch files)"
sudo systemctl reload php-fpm

trap - ERR
step "Maintenance mode off"
php artisan up

printf '\n\033[1;32m✓ Deployed %s → %s\033[0m\n' "$before" "$after"

#!/usr/bin/env bash
#
# بيت المونة — zero-surprise deploy for a single server.
#
#   cd /var/www/bait-almona && ./scripts/deploy.sh [git-ref]
#
# - Contains NO secrets: everything comes from the server's .env.
# - Stops on the first error (set -e) and always brings the site back up.
# - Migrations run with --force (never interactive) and are NOT rolled back
#   automatically — see docs/ROLLBACK.md before reverting anything.
#
set -Eeuo pipefail

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
REF="${1:-}"
PHP="${PHP:-php}"
COMPOSER="${COMPOSER:-composer}"

cd "$APP_DIR"

log() { printf '\n\033[1;32m==> %s\033[0m\n' "$*"; }

if [[ ! -f .env ]]; then
    echo "Missing .env — create it from .env.example first (see docs/PRODUCTION_CHECKLIST.md)." >&2
    exit 1
fi

# Bring the site back up whatever happens after maintenance mode starts.
MAINTENANCE=0
finish() {
    local status=$?
    if [[ "$MAINTENANCE" == "1" ]]; then
        log "Leaving maintenance mode"
        "$PHP" artisan up || true
    fi
    if [[ $status -ne 0 ]]; then
        echo "Deploy FAILED (exit $status). Check the output above and docs/ROLLBACK.md." >&2
    fi
}
trap finish EXIT

PREVIOUS_COMMIT="$(git rev-parse --short HEAD)"
log "Current commit: $PREVIOUS_COMMIT (write it down in case you need docs/ROLLBACK.md)"

log "Fetching code"
git fetch --tags origin
if [[ -n "$REF" ]]; then
    git checkout --quiet "$REF"
else
    git pull --ff-only
fi

log "Maintenance mode"
"$PHP" artisan down --retry=30 --refresh=15
MAINTENANCE=1

log "PHP dependencies"
"$COMPOSER" install --no-dev --optimize-autoloader --no-interaction --prefer-dist

log "Front-end build"
npm ci --no-audit --no-fund
npm run build

log "Database migrations"
"$PHP" artisan migrate --force

log "Default settings (never overwrites existing values)"
"$PHP" artisan db:seed --class=StoreSettingsSeeder --force

log "Storage link"
[[ -L public/storage ]] || "$PHP" artisan storage:link

log "Version"
git rev-parse --short HEAD > VERSION

log "Caches (config/routes/views/events). The data cache is NOT cleared:
     it holds rate limits and the scheduler heartbeat."
"$PHP" artisan optimize

log "Restart queue workers (they pick up the new code)"
"$PHP" artisan queue:restart

log "Back online"
"$PHP" artisan up
MAINTENANCE=0

log "Production check"
"$PHP" artisan store:check-production || {
    echo "store:check-production reported critical issues (the site is up; fix them now)." >&2
    exit 2
}

log "Deployed $(cat VERSION) (previous: $PREVIOUS_COMMIT)"

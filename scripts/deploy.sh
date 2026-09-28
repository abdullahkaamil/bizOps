#!/usr/bin/env bash
#
# Native deploy/update for BizOps (no Docker). Run it from the server after the
# working tree is at the target commit:
#
#   git pull --ff-only
#   RELEASE=$(git rev-parse --short HEAD) SMOKE_URL=https://example.com/up ./scripts/deploy.sh
#
# Aborts on the first failing step (set -e); safe to re-run after fixing a cause.
# First-time server setup is documented in docs/ubuntu-server-setup.md.

set -euo pipefail

cd "$(dirname "$0")/.."

RELEASE="${RELEASE:-$(git rev-parse --short HEAD)}"
BATCH_SIZE="${BATCH_SIZE:-50}"
SMOKE_URL="${SMOKE_URL:-}"

echo "==> Deploying release ${RELEASE}"

# Brief maintenance window while dependencies, assets and schema change.
php artisan down --retry=15 || true
trap 'php artisan up || true' EXIT

echo "==> PHP dependencies"
composer install --no-dev --optimize-autoloader --no-interaction

echo "==> Front-end build"
npm ci
npm run build

echo "==> Central migrations"
php artisan migrate --force

echo "==> Tenant migrations (batched, resumable)"
php artisan tenants:migrate-batched --release="${RELEASE}" --batch-size="${BATCH_SIZE}"

echo "==> Warm caches"
php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

echo "==> Restart queue workers onto new code"
php artisan queue:restart

echo "==> Validate environment & health"
php artisan app:validate-env
php artisan app:health-check

php artisan up
trap - EXIT

if [ -n "${SMOKE_URL}" ]; then
    echo "==> Smoke test ${SMOKE_URL}"
    curl --fail --silent --show-error "${SMOKE_URL}" > /dev/null && echo "    smoke ok"
fi

echo "==> Release ${RELEASE} deployed."

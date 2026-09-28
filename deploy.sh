#!/usr/bin/env bash
#
# Zero-surprise release script. Runs the deployment steps in the required order
# (docs/deployment.md) and aborts on the first failure. Idempotent: safe to
# re-run after fixing a failed step.
#
# Prerequisites: the new image is built and the code deployed to the web/worker
# containers, but workers have NOT yet been restarted onto it. Migrations must be
# backward compatible (expand-and-contract) so old code keeps serving traffic
# while this runs.
#
# Usage:
#   RELEASE=$(git rev-parse --short HEAD) ./deploy.sh
#
set -euo pipefail

RELEASE="${RELEASE:-$(date +%Y%m%d%H%M%S)}"
ARTISAN="${ARTISAN:-php artisan}"
BATCH_SIZE="${TENANT_BATCH_SIZE:-50}"

log() { printf '\n\033[1;34m==> %s\033[0m\n' "$1"; }

log "Release ${RELEASE}"

# 1. Validate the environment before touching anything.
log "1/8 Validating environment configuration"
$ARTISAN app:validate-env

# 2. Central migrations (backward compatible).
log "2/8 Running central migrations"
$ARTISAN migrate --force

# 3. Tenant migrations in controlled, resumable batches.
log "3/8 Migrating tenant databases (batch size ${BATCH_SIZE})"
if ! $ARTISAN tenants:migrate-batched --release="${RELEASE}" --batch-size="${BATCH_SIZE}"; then
    echo "Tenant migrations reported failures. Retry with:"
    echo "  $ARTISAN tenants:migrate-batched --release=${RELEASE} --retry-failed"
    exit 1
fi

# 4. Restart workers so they pick up the new code / schema.
log "4/8 Restarting queue workers"
$ARTISAN queue:restart

# 5. Warm caches.
log "5/8 Warming caches"
$ARTISAN config:cache
$ARTISAN route:cache
$ARTISAN event:cache

# 6. Health checks.
log "6/8 Running health checks"
$ARTISAN app:health-check

# 7. Smoke tests (HTTP). SMOKE_URL should hit the central health endpoint.
log "7/8 Running smoke tests"
if [ -n "${SMOKE_URL:-}" ]; then
    curl --fail --silent --show-error "${SMOKE_URL}" >/dev/null
    echo "Smoke URL ${SMOKE_URL} OK"
else
    echo "SMOKE_URL not set; skipping HTTP smoke test"
fi

# 8. Hand off to monitoring.
log "8/8 Release ${RELEASE} complete"
echo "Watch error tracking and queue depth for the next 15 minutes."

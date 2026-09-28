#!/usr/bin/env sh
set -e

# Warm the framework caches for whichever role this container plays. Config is
# validated first so a misconfigured release fails fast instead of booting into a
# broken state.
if [ "${SKIP_BOOT_TASKS:-false}" != "true" ]; then
    php artisan config:cache
    php artisan route:cache
    php artisan event:cache
fi

exec "$@"

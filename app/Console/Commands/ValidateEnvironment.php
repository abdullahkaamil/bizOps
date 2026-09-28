<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Validates that the runtime configuration is coherent and production-safe before
 * a release is promoted. Run in CI and as the first step of a deploy; a non-zero
 * exit blocks the release.
 *
 * Checks read from resolved config (not env()) so they behave correctly even when
 * the config cache is warm.
 */
class ValidateEnvironment extends Command
{
    protected $signature = 'app:validate-env {--json : Output machine-readable JSON}';

    protected $description = 'Validate environment configuration for the current environment';

    /** @var array<int, string> */
    protected array $errors = [];

    /** @var array<int, string> */
    protected array $warnings = [];

    public function handle(): int
    {
        $isProduction = app()->environment('production');

        $this->requireConfig('app.key', 'APP_KEY is not set (run php artisan key:generate).');
        $this->requireConfig('tenancy.central_domains', 'No central domains configured (CENTRAL_DOMAIN).');
        $this->requireConfig('database.connections.pgsql.database', 'Central database name is not set (DB_DATABASE).');

        // The queue jobs table must live in the central database, otherwise a
        // worker that boots into a tenant would look for jobs in the wrong place.
        $queueDriver = config('queue.default');
        if ($queueDriver === 'database') {
            $jobsConnection = config('queue.connections.database.connection');
            if ($jobsConnection === null || $jobsConnection === '') {
                $this->errors[] = 'DB_QUEUE_CONNECTION must pin the queue to the central connection (e.g. pgsql).';
            }
        }

        if ($isProduction) {
            $this->assert(config('app.debug') === false, 'APP_DEBUG must be false in production.');
            $this->assert(Str::startsWith((string) config('app.url'), 'https://'), 'APP_URL must use https in production.');
            $this->assert(config('session.driver') !== 'array', 'SESSION_DRIVER must be persistent (database/redis/cookie), not array.');
            $this->assert(config('cache.default') !== 'array', 'CACHE_STORE must be a shared store (redis), not array.');
            $this->assert(config('queue.default') !== 'sync', 'QUEUE_CONNECTION must not be sync in production.');

            // Recommendations — surfaced but non-blocking.
            $this->recommend(config('filesystems.default') === 's3', 'FILESYSTEM_DISK should be an S3-compatible disk in production so tenant files survive container restarts.');
            $this->recommend(! in_array(config('mail.default'), ['log', 'array'], true), 'MAIL_MAILER is not a real transport; outbound mail will not be delivered.');
            $this->recommend(config('session.secure') !== false, 'SESSION_SECURE_COOKIE should be true in production.');
        }

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'environment' => app()->environment(),
                'ok' => $this->errors === [],
                'errors' => $this->errors,
                'warnings' => $this->warnings,
            ], JSON_PRETTY_PRINT));

            return $this->errors === [] ? self::SUCCESS : self::FAILURE;
        }

        foreach ($this->warnings as $warning) {
            $this->warn('warn  '.$warning);
        }

        foreach ($this->errors as $error) {
            $this->error('error '.$error);
        }

        if ($this->errors !== []) {
            $this->newLine();
            $this->error(count($this->errors).' configuration error(s) found.');

            return self::FAILURE;
        }

        $this->info('Environment configuration OK ('.app()->environment().').');

        return self::SUCCESS;
    }

    protected function requireConfig(string $key, string $message): void
    {
        $value = config($key);
        if ($value === null || $value === '' || $value === []) {
            $this->errors[] = $message;
        }
    }

    protected function assert(bool $condition, string $message): void
    {
        if (! $condition) {
            $this->errors[] = $message;
        }
    }

    protected function recommend(bool $condition, string $message): void
    {
        if (! $condition) {
            $this->warnings[] = $message;
        }
    }
}

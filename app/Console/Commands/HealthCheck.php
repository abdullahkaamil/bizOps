<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Post-deploy smoke check for the core backing services. Exercised by the deploy
 * script after workers restart and by external uptime monitors (via the
 * scheduler or a lightweight HTTP wrapper). A non-zero exit signals a bad release.
 */
class HealthCheck extends Command
{
    protected $signature = 'app:health-check {--json : Output machine-readable JSON}';

    protected $description = 'Verify database, cache, storage and queue connectivity';

    public function handle(): int
    {
        $checks = [
            'database' => $this->guard(fn () => DB::connection()->select('select 1')),
            'cache' => $this->guard(function () {
                $key = 'health-check:'.uniqid('', true);
                Cache::put($key, '1', 5);
                $ok = Cache::get($key) === '1';
                Cache::forget($key);

                if (! $ok) {
                    throw new \RuntimeException('cache read-after-write mismatch');
                }
            }),
            'storage' => $this->guard(function () {
                $disk = Storage::disk(config('filesystems.default'));
                $path = 'health-check/'.uniqid('', true).'.txt';
                $disk->put($path, 'ok');
                $ok = $disk->get($path) === 'ok';
                $disk->delete($path);

                if (! $ok) {
                    throw new \RuntimeException('storage read-after-write mismatch');
                }
            }),
            'queue' => $this->guard(function () {
                // Resolving the connection proves the driver + its backing store
                // are configured. For the database queue this touches the central
                // connection the jobs table is pinned to.
                app('queue')->connection();
            }),
        ];

        $healthy = ! in_array(false, array_map(fn ($c) => $c['ok'], $checks), true);

        if ($this->option('json')) {
            $this->line((string) json_encode([
                'healthy' => $healthy,
                'checks' => $checks,
            ], JSON_PRETTY_PRINT));

            return $healthy ? self::SUCCESS : self::FAILURE;
        }

        $this->table(
            ['Check', 'Status', 'Detail'],
            array_map(
                fn (string $name, array $c) => [$name, $c['ok'] ? 'ok' : 'FAIL', $c['error'] ?? ''],
                array_keys($checks),
                array_values($checks),
            ),
        );

        if (! $healthy) {
            $this->error('Health check failed.');

            return self::FAILURE;
        }

        $this->info('All systems healthy.');

        return self::SUCCESS;
    }

    /**
     * @param  callable():mixed  $probe
     * @return array{ok: bool, error: string|null}
     */
    protected function guard(callable $probe): array
    {
        try {
            $probe();

            return ['ok' => true, 'error' => null];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }
}

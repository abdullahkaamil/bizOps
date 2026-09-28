<?php

declare(strict_types=1);

namespace App\Central\Console;

use App\Enums\TenantStatus;
use App\Models\Tenant;
use App\Models\TenantMigrationRun;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs the tenant migrations across many tenants in controlled batches, recording
 * per-tenant state so a release can be resumed and failures retried without
 * re-touching healthy tenants.
 *
 * Deployment order (see docs/deployment.md): central migrations first, then this
 * command, then restart workers. Migrations must be backward compatible
 * (expand-and-contract) because web containers on the old release may still be
 * serving traffic while tenants migrate.
 */
class MigrateTenantsBatched extends Command
{
    protected $signature = 'tenants:migrate-batched
        {--release= : Identifier for this deployment (defaults to a timestamp)}
        {--batch-size=50 : Number of tenants to migrate per batch}
        {--retry-failed : Only re-run tenants whose last attempt for this release failed}
        {--tenant= : Restrict to a single tenant (id or slug)}
        {--pretend : Show the SQL that would run without recording state}';

    protected $description = 'Migrate tenant databases in controlled, resumable batches';

    public function handle(): int
    {
        $releaseOption = $this->option('release');
        $release = is_string($releaseOption) && $releaseOption !== '' ? $releaseOption : now()->format('YmdHis');
        $batchSize = max(1, (int) $this->option('batch-size'));
        $pretend = (bool) $this->option('pretend');

        $tenants = $this->targetTenants($release);

        if ($tenants === []) {
            $this->info('No tenants to migrate.');

            return self::SUCCESS;
        }

        $this->info('Release ['.$release.']: migrating '.count($tenants)." tenant(s) in batches of {$batchSize}.");

        $failures = 0;
        $batchNumber = 0;

        foreach (array_chunk($tenants, $batchSize) as $batch) {
            $batchNumber++;
            $this->line('Batch '.$batchNumber.' ('.count($batch).' tenant(s))');

            foreach ($batch as $tenant) {
                if ($pretend) {
                    $this->pretendFor($tenant);

                    continue;
                }

                if (! $this->migrateTenant($tenant, $release, $batchNumber)) {
                    $failures++;
                }
            }
        }

        if ($failures > 0) {
            $this->error("{$failures} tenant(s) failed to migrate. Re-run with --retry-failed --release={$release} after investigating.");

            return self::FAILURE;
        }

        $this->info('All tenants migrated successfully.');

        return self::SUCCESS;
    }

    /**
     * @return array<int, Tenant>
     */
    protected function targetTenants(string $release): array
    {
        // Only tenants that own a live database. Deleted/failed/deletion-pending
        // tenants either never provisioned a database or are being torn down.
        $query = Tenant::query()->whereNotIn('status', [
            TenantStatus::Deleted->value,
            TenantStatus::Failed->value,
            TenantStatus::DeletionPending->value,
        ]);

        if ($needle = $this->option('tenant')) {
            $query->where(fn ($q) => $q->where('id', $needle)->orWhere('slug', $needle));
        }

        if ($this->option('retry-failed')) {
            $failedIds = TenantMigrationRun::query()
                ->where('release', $release)
                ->where('status', TenantMigrationRun::STATUS_FAILED)
                ->pluck('tenant_id');

            $query->whereIn('id', $failedIds);
        }

        // Collect into a typed array via an explicitly-typed cursor closure
        // (mirrors BackupTenants) — the eloquent collection generic is erased here.
        $tenants = [];
        $query->cursor()->each(function (Tenant $tenant) use (&$tenants): void {
            $tenants[] = $tenant;
        });

        return $tenants;
    }

    protected function migrateTenant(Tenant $tenant, string $release, int $batch): bool
    {
        $run = TenantMigrationRun::query()->firstOrNew([
            'tenant_id' => $tenant->id,
            'release' => $release,
        ]);

        $run->fill([
            'status' => TenantMigrationRun::STATUS_RUNNING,
            'batch' => $batch,
            'attempts' => $run->attempts + 1,
            'started_at' => $run->started_at ?? now(),
            'error_class' => null,
            'error_message' => null,
        ])->save();

        try {
            $output = $tenant->run(function (): string {
                Artisan::call('migrate', [
                    '--path' => [database_path('migrations/tenant')],
                    '--realpath' => true,
                    '--force' => true,
                ]);

                return Artisan::output();
            });

            $run->fill([
                'status' => TenantMigrationRun::STATUS_COMPLETED,
                'migrations_applied' => substr_count($output, 'DONE'),
                'output' => Str::limit($output, 60000, ''),
                'finished_at' => now(),
            ])->save();

            $this->line("  <info>ok</info>    {$tenant->id} ({$run->migrations_applied} applied)");

            return true;
        } catch (Throwable $e) {
            $run->fill([
                'status' => TenantMigrationRun::STATUS_FAILED,
                'error_class' => $e::class,
                'error_message' => Str::limit($e->getMessage(), 2000, ''),
                'finished_at' => now(),
            ])->save();

            $this->line("  <error>fail</error>  {$tenant->id}: {$e->getMessage()}");

            return false;
        }
    }

    protected function pretendFor(Tenant $tenant): void
    {
        $output = $tenant->run(function (): string {
            Artisan::call('migrate', [
                '--path' => [database_path('migrations/tenant')],
                '--realpath' => true,
                '--force' => true,
                '--pretend' => true,
            ]);

            return Artisan::output();
        });

        $this->line("  {$tenant->id}:");
        $this->line($output);
    }
}

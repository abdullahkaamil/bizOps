<?php

declare(strict_types=1);

namespace App\Central\Console;

use App\Central\Actions\BackupTenant;
use App\Enums\TenantStatus;
use App\Models\Tenant;
use Illuminate\Console\Command;

/**
 * Back up every active tenant (or one named tenant). Scheduled daily.
 */
class BackupTenants extends Command
{
    protected $signature = 'tenants:backup {tenant? : Tenant id or slug}';

    protected $description = 'Create an encrypted logical backup of each tenant database';

    public function handle(BackupTenant $backup): int
    {
        $query = Tenant::query()
            ->whereNotIn('status', [TenantStatus::Deleted->value, TenantStatus::Failed->value]);

        if ($needle = $this->argument('tenant')) {
            $query->where(fn ($q) => $q->where('id', $needle)->orWhere('slug', $needle));
        }

        $query->cursor()->each(function (Tenant $tenant) use ($backup): void {
            $path = $backup->handle($tenant);
            $this->info("Backed up {$tenant->id} -> {$path}");
        });

        return self::SUCCESS;
    }
}

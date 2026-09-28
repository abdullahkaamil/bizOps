<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Creates an encrypted logical backup of a tenant's database: every public table
 * exported to an encrypted JSON snapshot on a tenant-prefixed private path. This
 * is the application-level, test-covered backup; production additionally relies on
 * pg_dump / managed snapshots (see docs/backups.md).
 */
class BackupTenant
{
    public function handle(Tenant $tenant, string $disk = 'local'): string
    {
        $snapshot = $tenant->run(function () use ($tenant): array {
            $tables = collect(DB::select("select tablename from pg_tables where schemaname = 'public'"))
                ->pluck('tablename')->all();

            $data = [];
            foreach ($tables as $table) {
                $data[$table] = DB::table($table)->get()->map(fn ($row): array => (array) $row)->all();
            }

            return [
                'tenant_id' => $tenant->id,
                'taken_at' => now()->toIso8601String(),
                'tables' => $data,
            ];
        });

        $path = 'backups/'.$tenant->id.'/'.now()->format('Ymd_His').'.json.enc';
        // Encrypted at rest.
        Storage::disk($disk)->put($path, Crypt::encryptString((string) json_encode($snapshot)));

        return $path;
    }
}

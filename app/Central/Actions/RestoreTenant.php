<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * Restores a tenant database from an encrypted logical backup produced by
 * BackupTenant. FK constraints are deferred for the load (session_replication_role
 * = replica) so tables can be restored regardless of dependency order.
 *
 * A backup is not considered valid until this restore path is exercised — the
 * test suite does exactly that.
 */
class RestoreTenant
{
    public function handle(Tenant $tenant, string $path, string $disk = 'local'): void
    {
        if (! Storage::disk($disk)->exists($path)) {
            throw new RuntimeException("Backup not found at [{$path}].");
        }

        /** @var array{tables: array<string, array<int, array<string, mixed>>>} $snapshot */
        $snapshot = json_decode(Crypt::decryptString((string) Storage::disk($disk)->get($path)), true);

        $tenant->run(function () use ($snapshot): void {
            // Disable FK triggers for the load so tables can be replaced in any
            // order, then replace each table's contents atomically.
            DB::statement("SET session_replication_role = 'replica'");

            try {
                DB::transaction(function () use ($snapshot): void {
                    foreach ($snapshot['tables'] as $table => $rows) {
                        DB::table($table)->delete();
                        foreach (array_chunk($rows, 500) as $chunk) {
                            DB::table($table)->insert($chunk);
                        }
                    }
                });
            } finally {
                DB::statement("SET session_replication_role = 'origin'");
            }
        });
    }
}

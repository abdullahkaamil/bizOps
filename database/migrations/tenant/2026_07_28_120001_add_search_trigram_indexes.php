<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Trigram (pg_trgm) GIN indexes to keep ILIKE '%term%' global search fast on the
 * high-value columns (numbers, serials, names) as data grows. Created per tenant.
 */
return new class extends Migration
{
    /**
     * @var array<int, array{0: string, 1: string}>
     */
    private array $indexes = [
        ['customers', 'company_name'],
        ['service_jobs', 'number'],
        ['service_jobs', 'title'],
        ['customer_devices', 'serial_number'],
        ['workshop_tickets', 'number'],
        ['quotations', 'number'],
        ['inventory_items', 'sku'],
        ['inventory_items', 'name'],
        ['tasks', 'title'],
    ];

    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');

        foreach ($this->indexes as [$table, $column]) {
            $name = "{$table}_{$column}_trgm";
            DB::statement("CREATE INDEX IF NOT EXISTS {$name} ON {$table} USING gin ({$column} gin_trgm_ops)");
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as [$table, $column]) {
            DB::statement("DROP INDEX IF EXISTS {$table}_{$column}_trgm");
        }
    }
};

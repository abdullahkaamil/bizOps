<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Tracks the outcome of running the tenant migrations for a given release against
 * a single tenant database. Always written to the central database. Lets a
 * deployment resume mid-release and retry only the tenants that failed.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $release
 * @property string $status
 * @property int $batch
 * @property int $attempts
 * @property int $migrations_applied
 * @property string|null $output
 * @property string|null $error_class
 * @property string|null $error_message
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class TenantMigrationRun extends Model
{
    use CentralConnection;

    public const STATUS_PENDING = 'pending';

    public const STATUS_RUNNING = 'running';

    public const STATUS_COMPLETED = 'completed';

    public const STATUS_FAILED = 'failed';

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'batch' => 'integer',
            'attempts' => 'integer',
            'migrations_applied' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

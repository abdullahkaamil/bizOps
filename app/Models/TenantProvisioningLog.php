<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * Append-only record of each step taken while provisioning a tenant. Always
 * written to the central database, even when recorded from tenant context.
 *
 * @property int $id
 * @property string $tenant_id
 * @property string $step
 * @property string $status
 * @property string|null $message
 * @property string|null $error_class
 * @property Carbon|null $started_at
 * @property Carbon|null $finished_at
 */
class TenantProvisioningLog extends Model
{
    use CentralConnection;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}

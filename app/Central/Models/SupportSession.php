<?php

declare(strict_types=1);

namespace App\Central\Models;

use App\Support\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * An audited support-access (impersonation) session. Always central. There is no
 * silent access: every session records who, which tenant, why, and for how long.
 *
 * @property int $id
 * @property string $public_id
 * @property string $tenant_id
 * @property int|null $admin_id
 * @property string|null $admin_email
 * @property string $reason
 * @property CarbonInterface $expires_at
 * @property CarbonInterface|null $ended_at
 */
class SupportSession extends Model
{
    use CentralConnection, HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->ended_at === null && $this->expires_at->isFuture();
    }
}

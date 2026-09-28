<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single tenant-local company setting (EAV: group + key -> JSON value).
 * Lives in the tenant database. Encrypted values are handled by the
 * App\Domain\Settings\TenantSettings service, not here.
 *
 * @property string $group
 * @property string $key
 * @property mixed $value
 * @property bool $is_encrypted
 */
class TenantSetting extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'value' => 'json',
            'is_encrypted' => 'boolean',
        ];
    }
}

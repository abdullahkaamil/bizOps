<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $code
 * @property bool $is_default
 * @property bool $is_active
 */
class Warehouse extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * The tenant's default warehouse, creating one on first use.
     */
    public static function default(): self
    {
        return static::query()->where('is_default', true)->first()
            ?? static::query()->firstOrCreate(
                ['code' => 'MAIN'],
                ['name' => 'Main', 'is_default' => true, 'is_active' => true],
            );
    }
}

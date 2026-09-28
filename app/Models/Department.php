<?php

declare(strict_types=1);

namespace App\Models;

use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A tenant department. Users may belong to at most one department (MVP).
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $code
 * @property bool $is_active
 */
class Department extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}

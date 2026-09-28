<?php

declare(strict_types=1);

namespace App\Central\Models;

use App\Support\Concerns\HasPublicId;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Stancl\Tenancy\Database\Concerns\CentralConnection;

/**
 * A platform-wide system announcement shown to tenant users. Always central so it
 * is readable from any tenant context.
 *
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string $body
 * @property string $level
 * @property bool $is_active
 * @property CarbonInterface|null $starts_at
 * @property CarbonInterface|null $ends_at
 */
class Announcement extends Model
{
    use CentralConnection, HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @param  Builder<Announcement>  $query
     */
    public function scopeLive(Builder $query): void
    {
        $now = now();
        $query->where('is_active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now));
    }
}

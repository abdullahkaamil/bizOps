<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Models;

use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Immutable audit row for one workshop ticket transition.
 *
 * @property int $id
 * @property string $public_id
 * @property int $workshop_ticket_id
 * @property WorkshopStatus|null $from_status
 * @property WorkshopStatus $to_status
 * @property int|null $actor_id
 * @property array<string, mixed>|null $metadata
 * @property CarbonImmutable|null $created_at
 */
class WorkshopStatusHistory extends Model
{
    use HasPublicId;

    protected $table = 'workshop_status_history';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => WorkshopStatus::class,
            'to_status' => WorkshopStatus::class,
            'metadata' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<WorkshopTicket, $this>
     */
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(WorkshopTicket::class, 'workshop_ticket_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

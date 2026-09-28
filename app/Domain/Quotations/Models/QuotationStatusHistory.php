<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Models;

use App\Domain\Quotations\Enums\QuotationStatus;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $public_id
 * @property int $quotation_id
 * @property QuotationStatus|null $from_status
 * @property QuotationStatus $to_status
 * @property int|null $actor_id
 * @property string|null $reason
 * @property CarbonImmutable|null $created_at
 */
class QuotationStatusHistory extends Model
{
    use HasPublicId;

    protected $table = 'quotation_status_history';

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'from_status' => QuotationStatus::class,
            'to_status' => QuotationStatus::class,
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

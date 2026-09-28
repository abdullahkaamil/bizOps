<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Models;

use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An intake or repair image attached to a workshop ticket, stored privately and
 * processed asynchronously (compressed + thumbnailed).
 *
 * @property int $id
 * @property string $public_id
 * @property int $workshop_ticket_id
 * @property int|null $uploaded_by
 * @property string $kind
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property bool $processed
 * @property CarbonImmutable|null $created_at
 */
class WorkshopAttachment extends Model
{
    use HasPublicId;

    public $timestamps = false;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'processed' => 'boolean',
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
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

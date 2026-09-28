<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Models;

use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A captured customer signature stored as a private image file (never as a raw
 * Base64 blob in a text column).
 *
 * @property int $id
 * @property string $public_id
 * @property int $job_id
 * @property string|null $signed_by_name
 * @property string|null $signed_by_role
 * @property string $disk
 * @property string $path
 * @property string|null $mime_type
 * @property CarbonImmutable|null $created_at
 */
class JobSignature extends Model
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
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Job, $this>
     */
    public function job(): BelongsTo
    {
        return $this->belongsTo(Job::class);
    }
}

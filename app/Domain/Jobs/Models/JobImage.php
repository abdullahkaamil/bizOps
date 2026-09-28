<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Models;

use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A photo attached to a job, stored privately. The original is compressed and a
 * thumbnail generated asynchronously (see ProcessJobImage).
 *
 * @property int $id
 * @property string $public_id
 * @property int $job_id
 * @property int|null $uploaded_by
 * @property string $disk
 * @property string $path
 * @property string|null $thumbnail_path
 * @property string $original_name
 * @property string|null $mime_type
 * @property int $size_bytes
 * @property int $sort_order
 * @property bool $processed
 * @property CarbonImmutable|null $created_at
 */
class JobImage extends Model
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
            'sort_order' => 'integer',
            'processed' => 'boolean',
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

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}

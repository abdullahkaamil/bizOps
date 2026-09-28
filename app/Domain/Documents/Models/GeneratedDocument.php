<?php

declare(strict_types=1);

namespace App\Domain\Documents\Models;

use App\Domain\Documents\Enums\DocumentType;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\URL;

/**
 * A generated document (PDF/export) stored privately. Downloads always go through
 * a temporary signed, policy-checked route — the path here is never exposed.
 *
 * @property int $id
 * @property string $public_id
 * @property DocumentType $document_type
 * @property string $related_type
 * @property int $related_id
 * @property string|null $number
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property string|null $checksum
 * @property int|null $generated_by
 * @property CarbonImmutable|null $generated_at
 * @property array<string, mixed>|null $metadata
 */
class GeneratedDocument extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'document_type' => DocumentType::class,
            'size_bytes' => 'integer',
            'generated_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    /**
     * A short-lived signed URL for downloading this document.
     */
    public function temporaryDownloadUrl(int $minutes = 10): string
    {
        return URL::temporarySignedRoute(
            'tenant.documents.download',
            now()->addMinutes($minutes),
            ['document' => $this->public_id],
        );
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }
}

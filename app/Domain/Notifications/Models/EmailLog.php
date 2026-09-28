<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Domain\Notifications\Enums\EmailStatus;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * An audit record of one outbound email produced by a notification. Written by
 * TenantMailChannel on both success and failure.
 *
 * @property int $id
 * @property string $public_id
 * @property string $notification_type
 * @property string $recipient
 * @property string|null $subject
 * @property EmailStatus $status
 * @property string|null $provider_message_id
 * @property string|null $error_message
 * @property string|null $correlation_id
 * @property string|null $related_type
 * @property int|null $related_id
 * @property CarbonImmutable|null $sent_at
 */
class EmailLog extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => EmailStatus::class,
            'sent_at' => 'datetime',
        ];
    }
}

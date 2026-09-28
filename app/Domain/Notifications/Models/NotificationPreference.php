<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A user's channel opt-out for one notification type. Absence of a row means all
 * channels are enabled.
 *
 * @property int $id
 * @property int $user_id
 * @property string $notification_type
 * @property bool $email_enabled
 * @property bool $in_app_enabled
 */
class NotificationPreference extends Model
{
    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_enabled' => 'boolean',
            'in_app_enabled' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}

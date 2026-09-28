<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\NotificationPreference;
use App\Models\User;

/**
 * Resolves which channels a notification should use for a given user, honouring
 * their per-type opt-outs. The default (no row) is "all channels enabled".
 */
class NotificationPreferences
{
    public function emailEnabled(User $user, NotificationType $type): bool
    {
        $preference = $this->preference($user, $type);

        return $preference === null ? true : $preference->email_enabled;
    }

    public function inAppEnabled(User $user, NotificationType $type): bool
    {
        $preference = $this->preference($user, $type);

        return $preference === null ? true : $preference->in_app_enabled;
    }

    protected function preference(User $user, NotificationType $type): ?NotificationPreference
    {
        return NotificationPreference::query()
            ->where('user_id', $user->getKey())
            ->where('notification_type', $type->value)
            ->first();
    }
}

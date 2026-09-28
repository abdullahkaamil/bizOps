<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle status of a tenant user. Only Active users may authenticate.
 */
enum UserStatus: string
{
    case Invited = 'invited';
    case Active = 'active';
    case Suspended = 'suspended';

    public function canAuthenticate(): bool
    {
        return $this === self::Active;
    }
}

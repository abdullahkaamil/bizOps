<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Whether a tenant user is an internal employee or an external customer
 * representative with restricted access.
 */
enum UserType: string
{
    case Internal = 'internal';
    case External = 'external';
}

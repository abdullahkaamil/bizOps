<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant-local permissions. Stored as spatie permission records (guard: web)
 * inside each tenant database.
 */
enum Permission: string
{
    case ViewUsers = 'users.view';
    case CreateUsers = 'users.create';
    case UpdateUsers = 'users.update';
    case DeleteUsers = 'users.delete';
    case ManageRoles = 'roles.manage';
    case ViewActivity = 'activity.view';

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $permission): string => $permission->value, self::cases());
    }

    public function label(): string
    {
        return match ($this) {
            self::ViewUsers => 'View users',
            self::CreateUsers => 'Create users',
            self::UpdateUsers => 'Update users',
            self::DeleteUsers => 'Delete users',
            self::ManageRoles => 'Manage roles',
            self::ViewActivity => 'View activity log',
        };
    }
}

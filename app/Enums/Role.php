<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Tenant-local roles seeded into every tenant database.
 */
enum Role: string
{
    case Admin = 'Admin';
    case Manager = 'Manager';
    case Member = 'Member';

    /**
     * Permissions granted to this role.
     *
     * @return array<int, Permission>
     */
    public function permissions(): array
    {
        return match ($this) {
            self::Admin => Permission::cases(),
            self::Manager => [
                Permission::ViewUsers,
                Permission::CreateUsers,
                Permission::UpdateUsers,
                Permission::ViewActivity,
            ],
            self::Member => [
                Permission::ViewUsers,
            ],
        };
    }

    /**
     * @return array<int, string>
     */
    public function permissionValues(): array
    {
        return array_map(static fn (Permission $permission): string => $permission->value, $this->permissions());
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}

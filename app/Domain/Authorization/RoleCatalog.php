<?php

declare(strict_types=1);

namespace App\Domain\Authorization;

use App\Enums\Role;
use App\Enums\UserType;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

/**
 * Bridges the fixed, code-defined roles (the Role enum) with user-defined custom
 * roles stored in the tenant's spatie roles table.
 *
 *  - "System" roles are the enum roles: seeded into every tenant, protected from
 *    edit/delete so the permission matrix and its conformance tests stay intact.
 *  - Any other role in the database is a "custom" role, created through the Roles
 *    admin page. Custom roles are **internal-only** — the external trust boundary
 *    keeps a single fixed external role (customer_representative), so a mistaken
 *    custom role can never widen what an external customer can reach.
 */
class RoleCatalog
{
    /**
     * Role names reserved for external customer representatives. Everything else
     * (system or custom) is internal.
     *
     * @var array<int, string>
     */
    public const EXTERNAL = ['customer_representative'];

    public static function isSystem(string $name): bool
    {
        return in_array($name, Role::values(), true);
    }

    public static function userTypeFor(string $name): UserType
    {
        return in_array($name, self::EXTERNAL, true) ? UserType::External : UserType::Internal;
    }

    /**
     * Role names that may be assigned to a user of the given type (read live from
     * the tenant database, so custom roles are included).
     *
     * @return array<int, string>
     */
    public static function assignableFor(UserType $type): array
    {
        return self::names()
            ->filter(fn (string $name): bool => self::userTypeFor($name) === $type)
            ->values()->all();
    }

    /**
     * Every role name in the tenant database, ordered by name.
     *
     * @return Collection<int, string>
     */
    public static function names(): Collection
    {
        return SpatieRole::query()->orderBy('name')->pluck('name');
    }
}

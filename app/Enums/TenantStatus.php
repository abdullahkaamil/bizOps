<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Lifecycle status of a tenant (stored on the central tenants table). Deletion is
 * staged: active → suspended → archived → deletion_pending → deleted.
 */
enum TenantStatus: string
{
    case Provisioning = 'provisioning';
    case Active = 'active';
    case Suspended = 'suspended';
    case Failed = 'failed';
    case Archived = 'archived';
    case DeletionPending = 'deletion_pending';
    case Deleted = 'deleted';

    public function label(): string
    {
        return match ($this) {
            self::Provisioning => __('Provisioning'),
            self::Active => __('Active'),
            self::Suspended => __('Suspended'),
            self::Failed => __('Failed'),
            self::Archived => __('Archived'),
            self::DeletionPending => __('Deletion Pending'),
            self::Deleted => __('Deleted'),
        };
    }

    /**
     * Whether tenants in this status may access their workspace.
     */
    public function isAccessible(): bool
    {
        return $this === self::Active;
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $s): string => $s->value, self::cases());
    }
}

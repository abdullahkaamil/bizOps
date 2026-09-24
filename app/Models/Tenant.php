<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TenantStatus;
use Carbon\CarbonInterface;
use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Stancl\Tenancy\Contracts\TenantWithDatabase;
use Stancl\Tenancy\Database\Concerns\HasDatabase;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * @property string $id
 * @property string $name
 * @property string|null $slug
 * @property TenantStatus $status
 * @property string|null $plan_code
 * @property array<string, bool>|null $features
 * @property Carbon|null $license_expires_at
 * @property Carbon|null $trial_ends_at
 * @property Carbon|null $subscription_ends_at
 * @property Carbon|null $deletion_requested_at
 * @property Carbon|null $purge_after
 */
class Tenant extends BaseTenant implements TenantWithDatabase
{
    /** @use HasFactory<TenantFactory> */
    use HasDatabase, HasDomains, HasFactory;

    /**
     * Columns that are stored as real table columns rather than in the JSON `data` column.
     *
     * @return array<int, string>
     */
    public static function getCustomColumns(): array
    {
        return [
            'id',
            'name',
            'slug',
            'status',
            'plan_code',
            'features',
            'license_expires_at',
            'trial_ends_at',
            'subscription_ends_at',
            'deletion_requested_at',
            'purge_after',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TenantStatus::class,
            'features' => 'array',
            'license_expires_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'subscription_ends_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'purge_after' => 'datetime',
        ];
    }

    /**
     * Feature flags default to ON — a flag is disabled only when explicitly set
     * to false, so new modules light up for every tenant without a backfill.
     */
    public function hasFeature(string $feature): bool
    {
        return ($this->features[$feature] ?? true) === true;
    }

    public function setFeature(string $feature, bool $enabled): void
    {
        $features = $this->features ?? [];
        $features[$feature] = $enabled;
        $this->update(['features' => $features]);
    }

    /**
     * Whether a deletion-pending tenant's retention period has elapsed and it may
     * now be permanently purged.
     */
    public function retentionHasElapsed(): bool
    {
        return $this->purge_after !== null && $this->purge_after->isPast();
    }

    /**
     * The domains that route to this tenant.
     *
     * @return HasMany<Domain, $this>
     */
    public function domains(): HasMany
    {
        return $this->hasMany(Domain::class, 'tenant_id');
    }

    /**
     * Whether the tenant currently has a valid, non-expired license.
     */
    public function hasActiveLicense(): bool
    {
        return $this->license_expires_at !== null
            && $this->license_expires_at->isFuture();
    }

    /**
     * Whether the tenant's license has lapsed.
     */
    public function licenseHasExpired(): bool
    {
        return ! $this->hasActiveLicense();
    }

    /**
     * Whole days remaining on the license (negative if already expired, null if unset).
     */
    public function licenseDaysRemaining(): ?int
    {
        if ($this->license_expires_at === null) {
            return null;
        }

        return (int) Carbon::now()->startOfDay()->diffInDays(
            $this->license_expires_at->copy()->startOfDay(),
            false,
        );
    }

    /**
     * Extend (or set) the license expiry date. Renewing reactivates a tenant
     * that was suspended purely because its license lapsed.
     */
    public function renewLicenseUntil(CarbonInterface $expiresAt): void
    {
        $attributes = ['license_expires_at' => $expiresAt];

        if ($this->status === TenantStatus::Suspended) {
            $attributes['status'] = TenantStatus::Active;
        }

        $this->update($attributes);
    }

    /**
     * Whether the tenant may currently access its workspace: status is active
     * AND the license has not expired.
     */
    public function isAccessible(): bool
    {
        return $this->status === TenantStatus::Active && $this->hasActiveLicense();
    }
}

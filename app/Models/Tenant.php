<?php

declare(strict_types=1);

namespace App\Models;

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
 * @property Carbon|null $license_expires_at
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
            'license_expires_at',
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'license_expires_at' => 'datetime',
        ];
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
     * Extend (or set) the license expiry date.
     */
    public function renewLicenseUntil(CarbonInterface $expiresAt): void
    {
        $this->update(['license_expires_at' => $expiresAt]);
    }
}

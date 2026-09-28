<?php

declare(strict_types=1);

namespace App\Domain\Settings;

use App\Models\TenantSetting;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;

/**
 * Tenant-local company configuration, stored as EAV rows (group + key -> JSON)
 * in the tenant database. Reads fall back to the documented DEFAULTS below.
 * Encrypted values are transparently encrypted on write and decrypted on read.
 *
 * Pattern (documented in docs/tenancy.md): a single flexible tenant_settings
 * table rather than many strongly-typed tables, with typed accessors here.
 */
class TenantSettings
{
    /**
     * Default value for every known setting.
     *
     * @var array<string, array<string, mixed>>
     */
    public const DEFAULTS = [
        'general' => [
            'company_name' => null, 'legal_name' => null, 'email' => null,
            'phone' => null, 'address' => null, 'tax_number' => null,
        ],
        'branding' => [
            'logo' => null, 'primary_color' => '#111827', 'footer_text' => null,
        ],
        'localization' => [
            'timezone' => 'UTC', 'locale' => 'en', 'currency' => 'USD',
            'date_format' => 'Y-m-d', 'time_format' => 'H:i',
        ],
        'jobs' => [
            'number_prefix' => 'JOB', 'signature_required' => false, 'photo_required' => false,
            'min_service_notes' => 0,
        ],
        'workshop' => [
            'number_prefix' => 'WS', 'delivery_email_template' => null,
        ],
        'quotations' => [
            'number_prefix' => 'QUO', 'default_validity_days' => 30, 'default_tax_rate' => 0, 'terms' => null,
        ],
        'inventory' => [
            'allow_negative_stock' => false, 'default_warehouse' => 'Main', 'cost_visibility' => false,
            'low_stock_threshold' => 5,
        ],
        'notifications' => [
            'reminder_lead_time' => 24, 'email_sender_name' => null, 'reply_to' => null,
        ],
        'invitations' => [
            // When true, inviting a user creates the account immediately (with a
            // temporary password shown once) instead of waiting for the invitee
            // to open the accept link. Useful when no mail server is configured.
            'auto_accept' => false,
        ],
    ];

    public function get(string $group, string $key, mixed $default = null): mixed
    {
        $setting = TenantSetting::where('group', $group)->where('key', $key)->first();

        if ($setting === null) {
            return $default ?? (self::DEFAULTS[$group][$key] ?? null);
        }

        if ($setting->is_encrypted) {
            return json_decode(Crypt::decryptString((string) $setting->value), true);
        }

        return $setting->value;
    }

    public function set(string $group, string $key, mixed $value, bool $encrypted = false): void
    {
        TenantSetting::updateOrCreate(
            ['group' => $group, 'key' => $key],
            [
                'value' => $encrypted ? Crypt::encryptString((string) json_encode($value)) : $value,
                'is_encrypted' => $encrypted,
            ],
        );
    }

    public function forget(string $group, string $key): void
    {
        TenantSetting::where('group', $group)->where('key', $key)->delete();
    }

    // Typed accessors --------------------------------------------------------

    public function timezone(): string
    {
        return (string) $this->get('localization', 'timezone');
    }

    public function locale(): string
    {
        return (string) $this->get('localization', 'locale');
    }

    public function currency(): string
    {
        return (string) $this->get('localization', 'currency');
    }

    public function dateFormat(): string
    {
        return (string) $this->get('localization', 'date_format');
    }

    public function timeFormat(): string
    {
        return (string) $this->get('localization', 'time_format');
    }

    public function jobNumberPrefix(): string
    {
        return (string) $this->get('jobs', 'number_prefix');
    }

    public function jobsRequireSignature(): bool
    {
        return (bool) $this->get('jobs', 'signature_required');
    }

    public function jobsRequirePhoto(): bool
    {
        return (bool) $this->get('jobs', 'photo_required');
    }

    public function jobsMinServiceNotes(): int
    {
        return (int) $this->get('jobs', 'min_service_notes');
    }

    public function workshopNumberPrefix(): string
    {
        return (string) $this->get('workshop', 'number_prefix');
    }

    public function inventoryAllowsNegativeStock(): bool
    {
        return (bool) $this->get('inventory', 'allow_negative_stock');
    }

    public function inventoryLowStockThreshold(): float
    {
        return (float) $this->get('inventory', 'low_stock_threshold');
    }

    public function quotationNumberPrefix(): string
    {
        return (string) $this->get('quotations', 'number_prefix');
    }

    public function quotationValidityDays(): int
    {
        return (int) $this->get('quotations', 'default_validity_days');
    }

    public function quotationDefaultTaxRate(): float
    {
        return (float) $this->get('quotations', 'default_tax_rate');
    }

    public function autoAcceptsInvitations(): bool
    {
        return (bool) $this->get('invitations', 'auto_accept');
    }

    /**
     * Format a UTC timestamp in the tenant's timezone and format.
     */
    public function formatDateTime(CarbonInterface $utc, ?string $format = null): string
    {
        return Carbon::instance($utc)
            ->setTimezone($this->timezone())
            ->format($format ?? $this->dateFormat().' '.$this->timeFormat());
    }
}

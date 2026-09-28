<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Settings\TenantSettings;
use App\Enums\Currency;
use App\Http\Controllers\Controller;
use App\Models\TenantSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CompanySettingsController extends Controller
{
    public function __construct(protected TenantSettings $settings) {}

    /**
     * Show the tenant company settings (general + localization).
     */
    public function edit(): Response
    {
        $this->authorize('viewAny', TenantSetting::class);

        return Inertia::render('tenant/settings/Company', [
            'general' => [
                'company_name' => $this->settings->get('general', 'company_name'),
                'legal_name' => $this->settings->get('general', 'legal_name'),
                'email' => $this->settings->get('general', 'email'),
                'phone' => $this->settings->get('general', 'phone'),
                'address' => $this->settings->get('general', 'address'),
                'tax_number' => $this->settings->get('general', 'tax_number'),
            ],
            'localization' => [
                'timezone' => $this->settings->timezone(),
                'locale' => $this->settings->locale(),
                'currency' => $this->settings->currency(),
                'date_format' => $this->settings->dateFormat(),
                'time_format' => $this->settings->timeFormat(),
            ],
            'invitations' => [
                'auto_accept' => $this->settings->autoAcceptsInvitations(),
            ],
            'currencies' => Currency::options(),
        ]);
    }

    /**
     * Update the tenant company settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $this->authorize('update', TenantSetting::class);

        $validated = $request->validate([
            'company_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'tax_number' => ['nullable', 'string', 'max:100'],
            'timezone' => ['required', 'timezone'],
            'currency' => ['required', Rule::in(Currency::values())],
            'auto_accept_invitations' => ['boolean'],
        ]);

        foreach (['company_name', 'legal_name', 'email', 'phone', 'address', 'tax_number'] as $key) {
            $this->settings->set('general', $key, $validated[$key] ?? null);
        }

        $this->settings->set('localization', 'timezone', $validated['timezone']);
        $this->settings->set('localization', 'currency', $validated['currency']);
        $this->settings->set('invitations', 'auto_accept', $request->boolean('auto_accept_invitations'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Settings saved.')]);

        return to_route('tenant.settings.company');
    }
}

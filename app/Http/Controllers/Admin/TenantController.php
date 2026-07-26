<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tenancy\CreateTenant;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenewTenantRequest;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * List all tenants with their subdomain and license status.
     */
    public function index(): Response
    {
        $centralDomain = config('tenancy.central_domains')[0];
        $scheme = request()->getScheme();

        $tenants = DB::table('tenants')
            ->leftJoin('domains', 'domains.tenant_id', '=', 'tenants.id')
            ->orderByDesc('tenants.created_at')
            ->get([
                'tenants.id',
                'tenants.name',
                'tenants.created_at',
                'tenants.license_expires_at',
                'domains.domain as subdomain',
            ])
            ->map(function (object $row) use ($centralDomain, $scheme): array {
                $subdomain = $row->subdomain !== null ? (string) $row->subdomain : null;
                $expiresAt = $row->license_expires_at !== null ? Carbon::parse((string) $row->license_expires_at) : null;
                $active = $expiresAt !== null && $expiresAt->isFuture();

                return [
                    'id' => (string) $row->id,
                    'name' => (string) $row->name,
                    'subdomain' => $subdomain,
                    'url' => $subdomain ? "{$scheme}://{$subdomain}.{$centralDomain}" : null,
                    'created_at' => Carbon::parse((string) $row->created_at)->toIso8601String(),
                    'license_expires_at' => $expiresAt?->toIso8601String(),
                    'license_active' => $active,
                    'license_days_remaining' => $expiresAt !== null
                        ? (int) Carbon::now()->startOfDay()->diffInDays($expiresAt->copy()->startOfDay(), false)
                        : null,
                ];
            })
            ->all();

        return Inertia::render('admin/tenants/Index', [
            'tenants' => $tenants,
        ]);
    }

    /**
     * Show the create-tenant form.
     */
    public function create(): Response
    {
        return Inertia::render('admin/tenants/Create', [
            'centralDomain' => config('tenancy.central_domains')[0],
            'defaultLicenseExpiresAt' => Carbon::now()->addMonth()->toDateString(),
        ]);
    }

    /**
     * Provision a new tenant.
     */
    public function store(StoreTenantRequest $request, CreateTenant $createTenant): RedirectResponse
    {
        $createTenant->handle([
            'name' => $request->string('name')->toString(),
            'subdomain' => $request->string('subdomain')->toString(),
            'admin_name' => $request->string('admin_name')->toString(),
            'admin_email' => $request->string('admin_email')->toString(),
            'admin_password' => $request->string('admin_password')->toString(),
            'license_expires_at' => Carbon::parse($request->string('license_expires_at')->toString())->endOfDay(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant provisioned.')]);

        return to_route('admin.tenants.index');
    }

    /**
     * Renew (extend) a tenant's license.
     */
    public function renew(RenewTenantRequest $request, Tenant $tenant): RedirectResponse
    {
        $tenant->renewLicenseUntil(
            Carbon::parse($request->string('license_expires_at')->toString())->endOfDay(),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('License renewed.')]);

        return to_route('admin.tenants.index');
    }

    /**
     * Delete a tenant and drop its database.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant deleted.')]);

        return to_route('admin.tenants.index');
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Tenancy\CreateTenant;
use App\Central\Actions\ArchiveTenant;
use App\Central\Actions\PurgeTenant;
use App\Central\Actions\RequestTenantDeletion;
use App\Central\Actions\StartSupportSession;
use App\Central\Actions\TenantProvisioner;
use App\Central\Models\SupportSession;
use App\Enums\TenantStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\RenewTenantRequest;
use App\Http\Requests\Admin\RetryTenantRequest;
use App\Http\Requests\Admin\StoreTenantRequest;
use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TenantController extends Controller
{
    /**
     * Feature flags an operator may toggle per tenant.
     *
     * @var array<int, string>
     */
    private const FEATURES = ['jobs', 'workshop', 'inventory', 'quotations'];

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
                'tenants.status',
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
                    'status' => (string) $row->status,
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
     * Show a tenant with its provisioning timeline.
     */
    public function show(Tenant $tenant): Response
    {
        $centralDomain = config('tenancy.central_domains')[0];
        $subdomain = $tenant->domains()->value('domain');

        $timeline = TenantProvisioningLog::query()
            ->where('tenant_id', $tenant->getTenantKey())
            ->orderBy('id')
            ->get()
            ->map(fn (TenantProvisioningLog $log): array => [
                'id' => $log->id,
                'step' => $log->step,
                'status' => $log->status,
                'message' => $log->message,
                'finished_at' => $log->finished_at?->toIso8601String(),
            ])
            ->all();

        return Inertia::render('admin/tenants/Show', [
            'tenant' => [
                'id' => $tenant->getTenantKey(),
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status->value,
                'subdomain' => $subdomain,
                'url' => $subdomain ? request()->getScheme()."://{$subdomain}.{$centralDomain}" : null,
                'license_expires_at' => $tenant->license_expires_at?->toIso8601String(),
                'plan_code' => $tenant->plan_code,
                'trial_ends_at' => $tenant->trial_ends_at?->toDateString(),
                'subscription_ends_at' => $tenant->subscription_ends_at?->toDateString(),
                'features' => $tenant->features ?? [],
                'deletion_requested_at' => $tenant->deletion_requested_at?->toIso8601String(),
                'purge_after' => $tenant->purge_after?->toIso8601String(),
                'retention_elapsed' => $tenant->retentionHasElapsed(),
            ],
            'timeline' => $timeline,
            'availableFeatures' => self::FEATURES,
            'health' => $this->health($tenant),
            'supportSessions' => SupportSession::query()
                ->where('tenant_id', $tenant->getTenantKey())->latest('id')->limit(10)->get()
                ->map(fn (SupportSession $s): array => [
                    'id' => $s->public_id,
                    'admin' => $s->admin_email,
                    'reason' => $s->reason,
                    'expires_at' => $s->expires_at->toIso8601String(),
                    'active' => $s->isActive(),
                ])->all(),
        ]);
    }

    /**
     * Operational health metrics for a tenant. Read within the tenant database for
     * per-tenant figures; queue failures come from the central failed_jobs table.
     *
     * @return array<string, mixed>
     */
    protected function health(Tenant $tenant): array
    {
        $default = ['db_size' => null, 'migration_version' => null, 'last_activity' => null, 'users' => null];

        $metrics = $tenant->status === TenantStatus::Deleted
            ? $default
            : rescue(fn (): array => $tenant->run(function (): array {
                return [
                    'db_size' => (int) (DB::selectOne('select pg_database_size(current_database()) as size')->size ?? 0),
                    'migration_version' => DB::table('migrations')->orderByDesc('id')->value('migration'),
                    'last_activity' => optional(DB::table('activity_log')->max('created_at'))
                        ? Carbon::parse(DB::table('activity_log')->max('created_at'))->toIso8601String() : null,
                    'users' => DB::table('users')->whereNull('deleted_at')->count(),
                ];
            }), $default, false);

        $metrics['queue_failures'] = DB::connection(config('tenancy.database.central_connection'))
            ->table('failed_jobs')->where('payload', 'like', '%'.$tenant->getTenantKey().'%')->count();

        return $metrics;
    }

    /**
     * Retry provisioning for a failed tenant (idempotent).
     */
    public function retry(RetryTenantRequest $request, Tenant $tenant, TenantProvisioner $provisioner): RedirectResponse
    {
        $provisioner->retry($tenant, [
            'subdomain' => (string) ($tenant->slug ?? $tenant->domains()->value('domain')),
            'admin_name' => $request->string('admin_name')->toString(),
            'admin_email' => $request->string('admin_email')->toString(),
            'admin_password' => $request->string('admin_password')->toString(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Provisioning retried.')]);

        return to_route('admin.tenants.show', $tenant);
    }

    /**
     * Suspend an active tenant.
     */
    public function suspend(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => TenantStatus::Suspended]);

        TenantProvisioningLog::create([
            'tenant_id' => $tenant->id, 'step' => 'suspended', 'status' => 'completed',
            'message' => 'Tenant suspended by operator.', 'finished_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant suspended.')]);

        return to_route('admin.tenants.show', $tenant);
    }

    /**
     * Reactivate a suspended tenant.
     */
    public function reactivate(Tenant $tenant): RedirectResponse
    {
        $tenant->update(['status' => TenantStatus::Active]);

        TenantProvisioningLog::create([
            'tenant_id' => $tenant->id, 'step' => 'reactivated', 'status' => 'completed',
            'message' => 'Tenant reactivated by operator.', 'finished_at' => now(),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant reactivated.')]);

        return to_route('admin.tenants.show', $tenant);
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
     * Update a tenant's plan and subscription/trial metadata.
     */
    public function updatePlan(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'plan_code' => ['nullable', 'string', 'max:100'],
            'trial_ends_at' => ['nullable', 'date'],
            'subscription_ends_at' => ['nullable', 'date'],
        ]);

        $tenant->update([
            'plan_code' => $data['plan_code'] ?? null,
            'trial_ends_at' => $data['trial_ends_at'] ?? null,
            'subscription_ends_at' => $data['subscription_ends_at'] ?? null,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Plan updated.')]);

        return back();
    }

    /**
     * Toggle a tenant's feature flags.
     */
    public function updateFeatures(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'features' => ['required', 'array'],
            'features.*' => ['boolean'],
        ]);

        $features = array_intersect_key($data['features'], array_flip(self::FEATURES));
        $tenant->update(['features' => array_map('boolval', $features)]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Features updated.')]);

        return back();
    }

    /**
     * Archive a suspended tenant (the stage before deletion may be requested).
     */
    public function archive(Tenant $tenant, ArchiveTenant $action): RedirectResponse
    {
        $action->handle($tenant);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant archived.')]);

        return to_route('admin.tenants.show', $tenant);
    }

    /**
     * Request staged deletion of an archived tenant (requires double confirmation).
     */
    public function requestDeletion(Request $request, Tenant $tenant, RequestTenantDeletion $action): RedirectResponse
    {
        $data = $request->validate([
            'confirm' => ['accepted'],
            'confirm_name' => ['required', 'string'],
            'retention_days' => ['nullable', 'integer', 'min:1', 'max:365'],
        ]);

        // Second confirmation: the operator must retype the tenant name.
        abort_unless($data['confirm_name'] === $tenant->name, 422, 'Tenant name confirmation did not match.');

        $action->handle($tenant, true, (int) ($data['retention_days'] ?? 30), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Deletion scheduled after the retention period.')]);

        return to_route('admin.tenants.show', $tenant);
    }

    /**
     * Permanently purge a deletion-pending tenant (only after retention elapses).
     */
    public function purge(Tenant $tenant, PurgeTenant $action): RedirectResponse
    {
        $action->handle($tenant, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant purged.')]);

        return to_route('admin.tenants.index');
    }

    /**
     * Start an audited, time-limited support-access session.
     */
    public function support(Request $request, Tenant $tenant, StartSupportSession $action): RedirectResponse
    {
        $data = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
            'minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
        ]);

        $action->handle($tenant, $request->user(), $data['reason'], (int) ($data['minutes'] ?? 30));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Support session started and logged.')]);

        return to_route('admin.tenants.show', $tenant);
    }

    /**
     * Delete a tenant and drop its database. Kept for provisioning-failure cleanup;
     * the staged archive → deletion_pending → purge flow is the safe path for live
     * tenants.
     */
    public function destroy(Tenant $tenant): RedirectResponse
    {
        $tenant->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Tenant deleted.')]);

        return to_route('admin.tenants.index');
    }
}

<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use Carbon\CarbonInterface;
use Throwable;

/**
 * Coordinates the tenant provisioning steps. Every step is idempotent, so both
 * fresh provisioning and retrying a failed tenant run the same safe sequence:
 *
 *   record -> domain -> database -> migrate -> seed -> owner -> activate
 *
 * Each step is recorded in tenant_provisioning_logs (append-only) for the
 * central provisioning timeline. On failure the tenant is left non-active.
 */
class TenantProvisioner
{
    public function __construct(
        protected CreateTenantRecordAction $createRecord,
        protected CreateTenantDomainAction $createDomain,
        protected CreateTenantDatabaseAction $createDatabase,
        protected MigrateTenantDatabaseAction $migrate,
        protected SeedTenantDefaultsAction $seed,
        protected CreateTenantOwnerAction $createOwner,
        protected ActivateTenantAction $activate,
        protected MarkTenantProvisioningFailedAction $markFailed,
    ) {}

    /**
     * Provision a brand-new tenant.
     *
     * @param  array{name: string, subdomain: string, admin_name: string, admin_email: string, admin_password: string, license_expires_at?: CarbonInterface|string|null}  $data
     */
    public function provision(array $data): Tenant
    {
        $tenant = $this->createRecord->handle($data);

        return $this->run($tenant, $data);
    }

    /**
     * Retry provisioning for an existing (failed or half-provisioned) tenant.
     *
     * @param  array{subdomain: string, admin_name: string, admin_email: string, admin_password: string}  $data
     */
    public function retry(Tenant $tenant, array $data): Tenant
    {
        return $this->run($tenant, $data);
    }

    /**
     * @param  array{subdomain: string, admin_name: string, admin_email: string, admin_password: string}  $data
     */
    protected function run(Tenant $tenant, array $data): Tenant
    {
        $steps = [
            'domain' => fn () => $this->createDomain->handle($tenant, $data['subdomain']),
            'database' => fn () => $this->createDatabase->handle($tenant),
            'migrate' => fn () => $this->migrate->handle($tenant),
            'seed' => fn () => $this->seed->handle($tenant),
            'owner' => fn () => $this->createOwner->handle($tenant, [
                'name' => $data['admin_name'],
                'email' => $data['admin_email'],
                'password' => $data['admin_password'],
            ]),
        ];

        foreach ($steps as $step => $callback) {
            $log = $this->startLog($tenant, $step);

            try {
                $callback();
                $this->finishLog($log, 'completed');
            } catch (Throwable $e) {
                $this->finishLog($log, 'failed', $e);
                $this->markFailed->handle($tenant);

                throw $e;
            }
        }

        $this->activate->handle($tenant);
        $this->finishLog($this->startLog($tenant, 'activate'), 'completed');

        return $tenant->refresh();
    }

    protected function startLog(Tenant $tenant, string $step): TenantProvisioningLog
    {
        return TenantProvisioningLog::create([
            'tenant_id' => $tenant->getTenantKey(),
            'step' => $step,
            'status' => 'running',
            'started_at' => now(),
        ]);
    }

    protected function finishLog(TenantProvisioningLog $log, string $status, ?Throwable $e = null): void
    {
        $log->update([
            'status' => $status,
            'message' => $e?->getMessage(),
            'error_class' => $e ? $e::class : null,
            'finished_at' => now(),
        ]);
    }
}

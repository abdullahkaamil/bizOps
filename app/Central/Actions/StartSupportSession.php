<?php

declare(strict_types=1);

namespace App\Central\Actions;

use App\Central\Models\SupportSession;
use App\Models\Tenant;
use App\Models\TenantProvisioningLog;
use App\Models\User;

/**
 * Start an audited, time-limited support-access session for a tenant. A reason is
 * mandatory; every session is recorded — there is no silent impersonation.
 */
class StartSupportSession
{
    public function handle(Tenant $tenant, User $admin, string $reason, int $minutes = 30): SupportSession
    {
        $session = SupportSession::create([
            'tenant_id' => $tenant->id,
            'admin_id' => $admin->id,
            'admin_email' => $admin->email,
            'reason' => $reason,
            'expires_at' => now()->addMinutes(max(1, $minutes)),
        ]);

        TenantProvisioningLog::create(['tenant_id' => $tenant->id, 'step' => 'support_access', 'status' => 'completed', 'message' => "Support session by {$admin->email}: {$reason}", 'finished_at' => now()]);

        return $session;
    }
}

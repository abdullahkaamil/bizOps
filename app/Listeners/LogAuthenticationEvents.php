<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;

/**
 * Audits authentication events to the (tenant-local) activity log with the acting
 * user, so every login/logout is attributable. Failed attempts are recorded
 * without exposing the submitted password.
 */
class LogAuthenticationEvents
{
    public function handleLogin(Login $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            activity('auth')->causedBy($user)->performedOn($user)
                ->withProperties(['ip' => request()->ip()])
                ->event('login')->log('auth.login');
        }
    }

    public function handleLogout(Logout $event): void
    {
        $user = $event->user;

        if ($user instanceof User) {
            activity('auth')->causedBy($user)->performedOn($user)->event('logout')->log('auth.logout');
        }
    }

    public function handleFailed(Failed $event): void
    {
        // Never log credentials — record only the attempted username.
        activity('auth')
            ->withProperties(['email' => $event->credentials['email'] ?? null, 'ip' => request()->ip()])
            ->event('login_failed')->log('auth.login_failed');
    }
}

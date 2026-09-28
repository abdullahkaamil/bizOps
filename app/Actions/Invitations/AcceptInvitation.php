<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\UserStatus;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

/**
 * Accepts an invitation by its raw token and creates the tenant user. The token
 * is looked up by hash; expired or already-used invitations are rejected.
 */
class AcceptInvitation
{
    public function handle(string $token, string $name, string $password): User
    {
        $invitation = Invitation::where('token_hash', hash('sha256', $token))->first();

        if ($invitation === null) {
            throw ValidationException::withMessages(['token' => __('This invitation is invalid.')]);
        }

        if ($invitation->accepted_at !== null) {
            throw ValidationException::withMessages(['token' => __('This invitation has already been used.')]);
        }

        if ($invitation->expires_at->isPast()) {
            throw ValidationException::withMessages(['token' => __('This invitation has expired.')]);
        }

        $user = User::create([
            'name' => $name,
            'email' => $invitation->email,
            'password' => Hash::make($password),
            'user_type' => $invitation->user_type->value,
            'status' => UserStatus::Active->value,
            'customer_id' => $invitation->metadata['customer_id'] ?? null,
            'department_id' => $invitation->metadata['department_id'] ?? null,
            'email_verified_at' => now(),
        ]);

        if ($invitation->role !== null) {
            $user->assignRole($invitation->role);
        }

        $invitation->update(['accepted_at' => now()]);

        return $user;
    }
}

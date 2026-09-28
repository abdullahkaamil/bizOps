<?php

declare(strict_types=1);

namespace App\Actions\Invitations;

use App\Enums\UserType;
use App\Models\Invitation;
use App\Models\User;
use Illuminate\Support\Str;

/**
 * Creates a tenant invitation. Returns the invitation plus the raw token (shown
 * once, e.g. in the invite email/URL) — only its hash is persisted.
 */
class CreateInvitation
{
    /**
     * @return array{invitation: Invitation, token: string}
     */
    public function handle(
        string $email,
        UserType $userType = UserType::Internal,
        ?string $role = null,
        ?User $invitedBy = null,
        int $expiresInDays = 7,
        ?int $customerId = null,
        ?int $departmentId = null,
    ): array {
        $token = Str::random(48);

        $invitation = Invitation::create([
            'email' => $email,
            'user_type' => $userType->value,
            'token_hash' => hash('sha256', $token),
            'role' => $role,
            'expires_at' => now()->addDays($expiresInDays),
            'invited_by' => $invitedBy?->id,
            'metadata' => [
                'customer_id' => $customerId,
                'department_id' => $departmentId,
            ],
        ]);

        return ['invitation' => $invitation, 'token' => $token];
    }
}

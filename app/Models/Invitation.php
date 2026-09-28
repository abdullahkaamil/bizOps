<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\UserType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * A tenant-local invitation to join the tenant. The raw token is never stored;
 * only its SHA-256 hash. Lives in the tenant database (user_invitations).
 *
 * @property int $id
 * @property string $public_id
 * @property string $email
 * @property UserType $user_type
 * @property string $token_hash
 * @property string|null $role
 * @property Carbon $expires_at
 * @property Carbon|null $accepted_at
 * @property int|null $invited_by
 * @property array<string, mixed>|null $metadata
 */
class Invitation extends Model
{
    protected $table = 'user_invitations';

    protected $guarded = [];

    protected static function booted(): void
    {
        static::creating(function (Invitation $invitation): void {
            if (empty($invitation->public_id)) {
                $invitation->public_id = (string) Str::uuid();
            }
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'user_type' => UserType::class,
            'expires_at' => 'datetime',
            'accepted_at' => 'datetime',
            'metadata' => 'array',
        ];
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Models;

use App\Domain\CRM\Models\Customer;
use App\Domain\Tasks\Enums\BoardType;
use App\Models\User;
use App\Support\Concerns\HasPublicId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A Kanban-style board holding tasks. Internal boards drive employee-only work;
 * project boards are shared with a customer's external representatives.
 *
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string|null $description
 * @property BoardType $type
 * @property int|null $customer_id
 * @property bool $is_active
 * @property int|null $created_by
 */
class Board extends Model
{
    use HasPublicId, LogsActivity, SoftDeletes;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => BoardType::class,
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        // Every new board starts with a sensible set of default columns so it is
        // immediately usable; they can then be freely renamed / reordered / added.
        static::created(function (Board $board): void {
            BoardColumn::seedDefaults($board);
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'type', 'customer_id', 'is_active'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges()
            ->useLogName('board');
    }

    public function isProject(): bool
    {
        return $this->type === BoardType::Project;
    }

    public function isInternal(): bool
    {
        return $this->type === BoardType::Internal;
    }

    public function hasMember(User $user): bool
    {
        return $this->members()->whereKey($user->getKey())->exists();
    }

    /**
     * @return BelongsTo<Customer, $this>
     */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    /**
     * The board's steps, ordered left-to-right.
     *
     * @return HasMany<BoardColumn, $this>
     */
    public function columns(): HasMany
    {
        return $this->hasMany(BoardColumn::class)->orderBy('position');
    }

    /**
     * @return BelongsToMany<User, $this>
     */
    public function members(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'board_users')
            ->withPivot('role')
            ->withTimestamps();
    }
}

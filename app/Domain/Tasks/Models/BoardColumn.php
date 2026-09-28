<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Models;

use App\Domain\Tasks\Enums\ColumnAccess;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Enums\UserType;
use App\Support\Concerns\HasPublicId;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A user-defined step (Kanban column) on a board. Columns are ordered by
 * `position` and freely added / renamed / reordered / removed by internal staff.
 * The `category` maps the column to a coarse workflow bucket (a TaskStatus) so a
 * task's status stays coherent for dashboards, search and notifications no matter
 * how the columns are labelled.
 *
 * @property int $id
 * @property string $public_id
 * @property int $board_id
 * @property string $name
 * @property TaskStatus $category
 * @property ColumnAccess $move_in
 * @property ColumnAccess $move_out
 * @property int $position
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property-read Board $board
 */
class BoardColumn extends Model
{
    use HasPublicId;

    protected $guarded = [];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => TaskStatus::class,
            'move_in' => ColumnAccess::class,
            'move_out' => ColumnAccess::class,
            'position' => 'integer',
        ];
    }

    public function isTerminal(): bool
    {
        return $this->category->isTerminal();
    }

    public function allowsMoveIn(UserType $type): bool
    {
        return $this->move_in->allows($type);
    }

    public function allowsMoveOut(UserType $type): bool
    {
        return $this->move_out->allows($type);
    }

    /**
     * Seed a freshly created board with its default steps. Project boards get an
     * extra customer "review" step; internal boards do not.
     */
    public static function seedDefaults(Board $board): void
    {
        $defaults = [
            ['name' => 'Yapılacak', 'category' => TaskStatus::Todo, 'in' => ColumnAccess::Both, 'out' => ColumnAccess::Both],
            ['name' => 'Devam Ediyor', 'category' => TaskStatus::InProgress, 'in' => ColumnAccess::Both, 'out' => ColumnAccess::Both],
        ];

        if ($board->isProject()) {
            // The customer's court: the team submits work into review, and only the
            // customer moves it onward (approve) or back (reject); completion is the
            // customer's decision. Internal boards stay fully open.
            $defaults[] = ['name' => 'İncelemede', 'category' => TaskStatus::Review, 'in' => ColumnAccess::Both, 'out' => ColumnAccess::External];
            $defaults[] = ['name' => 'Tamamlandı', 'category' => TaskStatus::Completed, 'in' => ColumnAccess::External, 'out' => ColumnAccess::Both];
        } else {
            $defaults[] = ['name' => 'Tamamlandı', 'category' => TaskStatus::Completed, 'in' => ColumnAccess::Both, 'out' => ColumnAccess::Both];
        }

        foreach ($defaults as $position => $column) {
            $board->columns()->create([
                'name' => $column['name'],
                'category' => $column['category'],
                'move_in' => $column['in'],
                'move_out' => $column['out'],
                'position' => $position,
            ]);
        }
    }

    /**
     * @return BelongsTo<Board, $this>
     */
    public function board(): BelongsTo
    {
        return $this->belongsTo(Board::class);
    }

    /**
     * @return HasMany<Task, $this>
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }
}

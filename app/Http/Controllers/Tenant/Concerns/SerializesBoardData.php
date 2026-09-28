<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Concerns;

use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\BoardColumn;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskAttachment;
use App\Domain\Tasks\Models\TaskComment;
use App\Domain\Tasks\Models\TaskStatusHistory;
use App\Models\User;

/**
 * Shared serialization for board/task payloads sent to Inertia. Every payload
 * embeds the viewer's *server-computed* abilities so the frontend only ever
 * mirrors authorization — it never decides it.
 */
trait SerializesBoardData
{
    /**
     * @return array<string, mixed>
     */
    protected function boardSummary(Board $board): array
    {
        return [
            'id' => $board->public_id,
            'name' => $board->name,
            'description' => $board->description,
            'type' => $board->type->value,
            'is_active' => $board->is_active,
            'customer' => $board->relationLoaded('customer') && $board->customer
                ? ['id' => $board->customer->public_id, 'name' => $board->customer->company_name]
                : null,
            'tasks_count' => $board->tasks_count ?? null,
            'members_count' => $board->members_count ?? null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function taskCard(Task $task, User $viewer): array
    {
        return [
            'id' => $task->public_id,
            'title' => $task->title,
            'status' => $task->status->value,
            'column_id' => $task->relationLoaded('column') && $task->column
                ? $task->column->public_id
                : null,
            'priority' => $task->priority?->value,
            'due_at' => $task->due_at?->toIso8601String(),
            'completed_at' => $task->completed_at?->toIso8601String(),
            'assignees' => $task->relationLoaded('assignees')
                ? $task->assignees->map(fn (User $u): array => $this->userChip($u))->all()
                : [],
            'abilities' => $this->taskAbilities($task, $viewer),
        ];
    }

    /**
     * The full task drawer: base card plus description, comments, attachments and
     * the immutable status history — comments/attachments filtered by visibility.
     *
     * @return array<string, mixed>
     */
    protected function taskDetail(Task $task, User $viewer): array
    {
        return [
            ...$this->taskCard($task, $viewer),
            'description' => $task->description,
            'board_id' => $task->board->public_id,
            'comments' => $task->comments
                ->filter(fn (TaskComment $c): bool => $this->canSeeVisibility($viewer, $c->visibility))
                ->map(fn (TaskComment $c): array => [
                    'id' => $c->public_id,
                    'body' => $c->body,
                    'visibility' => $c->visibility->value,
                    'author' => $c->author ? $this->userChip($c->author) : null,
                    'mine' => $c->user_id === $viewer->id,
                    'created_at' => $c->created_at?->toIso8601String(),
                ])->values()->all(),
            'attachments' => $task->attachments
                ->filter(fn (TaskAttachment $a): bool => $this->canSeeVisibility($viewer, $a->visibility))
                ->map(fn (TaskAttachment $a): array => [
                    'id' => $a->public_id,
                    'name' => $a->original_name,
                    'size' => $a->size_bytes,
                    'visibility' => $a->visibility->value,
                    'uploaded_by' => $a->uploader ? $this->userChip($a->uploader) : null,
                    'mine' => $a->uploaded_by === $viewer->id,
                    'created_at' => $a->created_at?->toIso8601String(),
                ])->values()->all(),
            'history' => $task->statusHistory->map(fn (TaskStatusHistory $h): array => [
                'id' => $h->public_id,
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'reason' => $h->reason,
                'actor' => $h->actor ? $this->userChip($h->actor) : null,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->all(),
        ];
    }

    /**
     * @return array<string, bool>
     */
    protected function taskAbilities(Task $task, User $viewer): array
    {
        return [
            'move' => $viewer->can('move', $task),
            'update' => $viewer->can('update', $task),
            'delete' => $viewer->can('delete', $task),
            'comment_internal' => $viewer->can('comment', [$task, CommentVisibility::Internal]),
            'comment_customer' => $viewer->can('comment', [$task, CommentVisibility::Customer]),
        ];
    }

    /**
     * A board's steps in display order, with the tasks currently in each one.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function boardColumns(Board $board, User $viewer): array
    {
        return $board->columns->map(fn (BoardColumn $column): array => [
            'id' => $column->public_id,
            'name' => $column->name,
            'category' => $column->category->value,
            'position' => $column->position,
            'move_in' => $column->move_in->value,
            'move_out' => $column->move_out->value,
            // Viewer-specific: may this viewer drop a card into / pull a card out
            // of this step? The frontend uses these to gate drag-and-drop.
            'can_drop' => $column->allowsMoveIn($viewer->user_type),
            'can_pull' => $column->allowsMoveOut($viewer->user_type),
            'tasks' => $board->tasks
                ->where('board_column_id', $column->id)
                ->sortBy([['position', 'asc'], ['id', 'asc']])
                ->map(fn (Task $task): array => $this->taskCard($task, $viewer))
                ->values()->all(),
        ])->values()->all();
    }

    protected function canSeeVisibility(User $viewer, CommentVisibility $visibility): bool
    {
        // Internal staff see everything; external reps only customer-visible items.
        return $viewer->isInternal() || $visibility === CommentVisibility::Customer;
    }

    /**
     * @return array<string, mixed>
     */
    protected function userChip(User $user): array
    {
        return [
            'id' => $user->public_id,
            'name' => $user->name,
        ];
    }
}

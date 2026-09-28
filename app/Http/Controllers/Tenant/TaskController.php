<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Notifications\Notifications\TaskAssignedNotification;
use App\Domain\Notifications\Notifications\TaskCreatedNotification;
use App\Domain\Tasks\Actions\MoveTaskAction;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\BoardColumn;
use App\Domain\Tasks\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\MoveTaskRequest;
use App\Http\Requests\Tenant\StoreTaskRequest;
use App\Http\Requests\Tenant\UpdateTaskRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification as NotificationFacade;
use Inertia\Inertia;

class TaskController extends Controller
{
    public function store(StoreTaskRequest $request, Board $board): RedirectResponse
    {
        $this->authorize('create', [Task::class, $board]);

        $data = $request->validated();

        $task = DB::transaction(function () use ($board, $data, $request): Task {
            $task = $board->tasks()->create([
                'title' => $data['title'],
                'description' => $data['description'] ?? null,
                'status' => TaskStatus::Todo,
                'priority' => $data['priority'] ?? null,
                'due_at' => $data['due_at'] ?? null,
                'created_by' => $request->user()->id,
            ]);

            $this->syncAssignees($task, $data['assignee_ids'] ?? []);

            return $task;
        });

        $this->notifyBoardOfNewTask($task, $board, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task created.')]);

        return to_route('tenant.boards.show', $board);
    }

    /**
     * Tell every active board member (internal and external) — except the creator —
     * that a new task was opened.
     */
    protected function notifyBoardOfNewTask(Task $task, Board $board, int $actorId): void
    {
        $recipients = $board->members()->get()
            ->filter(fn (User $u): bool => $u->isActive() && $u->id !== $actorId)
            ->values();

        if ($recipients->isEmpty()) {
            return;
        }

        NotificationFacade::send($recipients, new TaskCreatedNotification(
            $task->id, $task->title, $task->public_id, $board->public_id, $board->name,
        ));
    }

    public function update(UpdateTaskRequest $request, Task $task): RedirectResponse
    {
        $this->authorize('update', $task);

        $data = $request->validated();

        DB::transaction(function () use ($task, $data): void {
            // Title + description are immutable (the task's definition); only
            // workflow metadata may change here.
            $task->update([
                'priority' => $data['priority'] ?? null,
                'due_at' => $data['due_at'] ?? null,
            ]);

            if (array_key_exists('assignee_ids', $data)) {
                $this->syncAssignees($task, $data['assignee_ids'] ?? []);
            }
        });

        $this->notifyAssignees($task, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task updated.')]);

        return back();
    }

    /**
     * Move a task to any column on its board (free movement — drag-and-drop on the
     * Kanban, or the "move to step" dropdown in the task drawer).
     */
    public function move(MoveTaskRequest $request, Task $task, MoveTaskAction $action): RedirectResponse
    {
        $this->authorize('move', $task);

        $column = BoardColumn::where('public_id', $request->validated('column_id'))->firstOrFail();

        $position = $request->validated('position');

        $action->handle($task, $request->user(), $column, $position !== null ? (int) $position : null);

        return back();
    }

    public function destroy(Task $task): RedirectResponse
    {
        $this->authorize('delete', $task);

        $board = $task->board;
        $task->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Task deleted.')]);

        return to_route('tenant.boards.show', $board);
    }

    /**
     * Resolve assignee public ids to board members and sync them. The request
     * validation guarantees every resolved user already belongs to the board.
     * Newly attached ids are stashed for post-commit notification.
     *
     * @param  array<int, string>  $publicIds
     */
    protected function syncAssignees(Task $task, array $publicIds): void
    {
        $ids = $publicIds === []
            ? []
            : User::whereIn('public_id', $publicIds)->pluck('id')->all();

        $changes = $task->assignees()->sync($ids);
        $this->attachedAssigneeIds = array_map('intval', $changes['attached']);
    }

    /**
     * Notify users who were newly assigned in this request (excluding the actor).
     */
    protected function notifyAssignees(Task $task, int $actorId): void
    {
        $ids = array_filter($this->attachedAssigneeIds, fn (int $id): bool => $id !== $actorId);

        if ($ids === []) {
            return;
        }

        $users = User::whereIn('id', $ids)->get();

        NotificationFacade::send($users, new TaskAssignedNotification(
            $task->id, $task->title, $task->public_id, $task->board->public_id,
        ));
    }

    /** @var array<int, int> */
    protected array $attachedAssigneeIds = [];
}

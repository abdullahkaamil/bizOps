<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Notifications\Notifications\TaskCommentedNotification;
use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Models\Task;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTaskCommentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TaskCommentController extends Controller
{
    public function store(StoreTaskCommentRequest $request, Task $task): RedirectResponse
    {
        $visibility = $task->board->isInternal()
            ? CommentVisibility::Internal
            : CommentVisibility::from($request->validated('visibility'));

        // Authorization is visibility-aware: external reps can only post
        // customer-visible comments, and never on completed tasks.
        $this->authorize('comment', [$task, $visibility]);

        $task->comments()->create([
            'user_id' => $request->user()->id,
            'body' => $request->validated('body'),
            'visibility' => $visibility,
        ]);

        $this->notifyBoard($task, $visibility, $request->user()->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Comment added.')]);

        return back();
    }

    /**
     * Notify board members of the new comment — respecting visibility: an internal
     * comment never reaches an external representative.
     */
    protected function notifyBoard(Task $task, CommentVisibility $visibility, int $actorId): void
    {
        $board = $task->board;

        $recipients = $board->members()->get()->filter(function (User $u) use ($visibility, $actorId): bool {
            if ($u->id === $actorId || ! $u->isActive()) {
                return false;
            }

            // Internal-only comments are hidden from external representatives.
            return $visibility === CommentVisibility::Customer || $u->isInternal();
        })->values();

        if ($recipients->isEmpty()) {
            return;
        }

        Notification::send($recipients, new TaskCommentedNotification(
            $task->id, $task->title, $task->public_id, $board->public_id, $board->name,
        ));
    }
}

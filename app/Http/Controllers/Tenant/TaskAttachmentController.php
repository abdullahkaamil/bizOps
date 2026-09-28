<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Tasks\Enums\CommentVisibility;
use App\Domain\Tasks\Models\Task;
use App\Domain\Tasks\Models\TaskAttachment;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreTaskAttachmentRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TaskAttachmentController extends Controller
{
    public function store(StoreTaskAttachmentRequest $request, Task $task): RedirectResponse
    {
        $visibility = $task->board->isInternal()
            ? CommentVisibility::Internal
            : CommentVisibility::from($request->validated('visibility'));

        $this->authorize('attach', [$task, $visibility]);

        $file = $request->file('file');
        // Namespaced by tenant so files never collide across tenant databases.
        $directory = 'task-attachments/'.tenant('id');
        $path = $file->store($directory, 'local');

        $task->attachments()->create([
            'uploaded_by' => $request->user()->id,
            'disk' => 'local',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getClientMimeType(),
            'size_bytes' => $file->getSize(),
            'visibility' => $visibility,
            'created_at' => now(),
        ]);

        return back();
    }

    public function download(Request $request, TaskAttachment $attachment): StreamedResponse
    {
        $attachment->load('task.board');

        // Downloading is gated by the same visibility rule as viewing: an external
        // rep can never pull an internal attachment.
        $this->authorize('view', $attachment->task);

        abort_unless(
            $this->canSeeVisibility($request->user(), $attachment),
            403,
        );

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name);
    }

    protected function canSeeVisibility(User $user, TaskAttachment $attachment): bool
    {
        return $user->isInternal() || $attachment->visibility === CommentVisibility::Customer;
    }
}

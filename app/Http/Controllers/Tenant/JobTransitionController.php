<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Jobs\Actions\AssignJobAction;
use App\Domain\Jobs\Actions\CancelJobAction;
use App\Domain\Jobs\Actions\CompleteJobAction;
use App\Domain\Jobs\Actions\ReopenJobAction;
use App\Domain\Jobs\Actions\StartJobAction;
use App\Domain\Jobs\Models\Job;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AssignJobRequest;
use App\Http\Requests\Tenant\CancelJobRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

/**
 * Explicit, verb-per-endpoint job transitions. There is no generic status
 * setter — each lifecycle move is its own authorized action.
 */
class JobTransitionController extends Controller
{
    public function start(Request $request, Job $job, StartJobAction $action): RedirectResponse
    {
        $this->authorize('start', $job);
        $action->handle($job, $request->user());

        return $this->done(__('Job started.'));
    }

    public function complete(Request $request, Job $job, CompleteJobAction $action): RedirectResponse
    {
        $this->authorize('complete', $job);
        $action->handle($job, $request->user());

        return $this->done(__('Job completed.'));
    }

    public function cancel(CancelJobRequest $request, Job $job, CancelJobAction $action): RedirectResponse
    {
        $this->authorize('cancel', $job);
        $action->handle($job, $request->user(), $request->validated('reason'));

        return $this->done(__('Job canceled.'));
    }

    public function reopen(Request $request, Job $job, ReopenJobAction $action): RedirectResponse
    {
        $this->authorize('reopen', $job);
        $action->handle($job, $request->user());

        return $this->done(__('Job reopened.'));
    }

    public function assign(AssignJobRequest $request, Job $job, AssignJobAction $action): RedirectResponse
    {
        $this->authorize('assign', $job);

        $assignee = User::where('public_id', $request->validated('assigned_user_id'))->firstOrFail();
        $action->handle($job, $assignee, $request->user());

        return $this->done(__('Job assigned.'));
    }

    protected function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Jobs\Models\Job;
use App\Domain\Workshop\Actions\CompleteWorkshopTicketAction;
use App\Domain\Workshop\Actions\DeliverWorkshopTicketAction;
use App\Domain\Workshop\Actions\LinkWorkshopTicketToJobAction;
use App\Domain\Workshop\Actions\UnlinkWorkshopTicketFromJobAction;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\CompleteWorkshopTicketRequest;
use App\Http\Requests\Tenant\LinkWorkshopJobRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class WorkshopTransitionController extends Controller
{
    public function complete(CompleteWorkshopTicketRequest $request, WorkshopTicket $ticket, CompleteWorkshopTicketAction $action): RedirectResponse
    {
        $this->authorize('complete', $ticket);
        $action->handle($ticket, $request->user(), $request->validated('repair_notes'));

        return $this->done(__('Ticket completed.'));
    }

    public function deliver(Request $request, WorkshopTicket $ticket, DeliverWorkshopTicketAction $action): RedirectResponse
    {
        $this->authorize('deliver', $ticket);
        $action->handle($ticket, $request->user());

        return $this->done(__('Ticket delivered and customer notified.'));
    }

    public function link(LinkWorkshopJobRequest $request, WorkshopTicket $ticket, LinkWorkshopTicketToJobAction $action): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $job = Job::where('public_id', $request->validated('job_id'))->firstOrFail();
        $action->handle($ticket, $job, $request->user());

        return $this->done(__('Job linked.'));
    }

    public function unlink(Request $request, WorkshopTicket $ticket, UnlinkWorkshopTicketFromJobAction $action): RedirectResponse
    {
        $this->authorize('update', $ticket);
        $action->handle($ticket, $request->user());

        return $this->done(__('Job unlinked.'));
    }

    protected function done(string $message): RedirectResponse
    {
        Inertia::flash('toast', ['type' => 'success', 'message' => $message]);

        return back();
    }
}

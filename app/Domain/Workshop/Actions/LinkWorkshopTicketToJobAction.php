<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions;

use App\Domain\Jobs\Models\Job;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;

/**
 * Link a workshop ticket to a service job. While linked and unresolved, the job
 * cannot be completed (see CompleteJobAction::assertWorkshopConstraints).
 */
class LinkWorkshopTicketToJobAction
{
    public function handle(WorkshopTicket $ticket, Job $job, User $actor): WorkshopTicket
    {
        $ticket->update(['job_id' => $job->id]);

        activity('workshop')->performedOn($ticket)->causedBy($actor)
            ->withProperties(['job_id' => $job->id])->event('linked')->log('workshop.linked_to_job');

        return $ticket->refresh();
    }
}

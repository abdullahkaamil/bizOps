<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions;

use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;

class UnlinkWorkshopTicketFromJobAction
{
    public function handle(WorkshopTicket $ticket, User $actor): WorkshopTicket
    {
        $ticket->update(['job_id' => null]);

        activity('workshop')->performedOn($ticket)->causedBy($actor)
            ->event('unlinked')->log('workshop.unlinked_from_job');

        return $ticket->refresh();
    }
}

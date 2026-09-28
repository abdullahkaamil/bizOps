<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions;

use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Exceptions\InvalidWorkshopTransition;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;

/**
 * Update a ticket's repair notes while it is still in progress.
 */
class UpdateRepairNotesAction
{
    public function handle(WorkshopTicket $ticket, User $actor, ?string $repairNotes): WorkshopTicket
    {
        if ($ticket->status !== WorkshopStatus::InProgress) {
            throw InvalidWorkshopTransition::wrongStatus($ticket->status, [WorkshopStatus::InProgress]);
        }

        $ticket->update(['repair_notes' => $repairNotes]);

        activity('workshop')->performedOn($ticket)->causedBy($actor)
            ->event('repair_notes_updated')->log('workshop.repair_notes_updated');

        return $ticket->refresh();
    }
}

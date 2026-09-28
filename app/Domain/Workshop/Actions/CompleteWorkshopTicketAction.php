<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions;

use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Workshop\Actions\Concerns\RecordsWorkshopTransition;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Events\WorkshopCompleted;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Complete a repair. Repair notes are mandatory; the completion time is recorded
 * from the server. A completion report is generated so the ticket can move to the
 * waiting-for-delivery stage with documentation.
 */
class CompleteWorkshopTicketAction
{
    use RecordsWorkshopTransition;

    public function __construct(private DocumentService $documents) {}

    public function handle(WorkshopTicket $ticket, User $actor, ?string $repairNotes = null): WorkshopTicket
    {
        $this->guardStatus($ticket, WorkshopStatus::InProgress);

        if ($repairNotes !== null && trim($repairNotes) !== '') {
            $ticket->repair_notes = $repairNotes;
        }

        if (trim((string) $ticket->repair_notes) === '') {
            throw ValidationException::withMessages([
                'repair_notes' => __('Repair notes are required before completing a ticket.'),
            ]);
        }

        $ticket = $this->transition(
            $ticket,
            $actor,
            WorkshopStatus::Completed,
            attributes: ['repair_notes' => $ticket->repair_notes, 'completed_at' => now(), 'completed_by' => $actor->id],
            afterCommit: fn () => WorkshopCompleted::dispatch($ticket->id, $actor->id),
        );

        // Completion report (private PDF) — generated outside the transaction.
        $this->documents->generate(DocumentType::WorkshopCompletionReport, $ticket, $actor);

        return $ticket->refresh();
    }
}

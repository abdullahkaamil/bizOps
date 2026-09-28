<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Actions;

use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Notifications\Notifications\WorkshopDeliveredNotification;
use App\Domain\Workshop\Actions\Concerns\RecordsWorkshopTransition;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Events\WorkshopDelivered;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;
use Illuminate\Support\Facades\Notification;

/**
 * Deliver a completed ticket: record the delivery time and queue the customer
 * an email with the existing completion PDF attached.
 */
class DeliverWorkshopTicketAction
{
    use RecordsWorkshopTransition;

    public function __construct(private DocumentService $documents) {}

    public function handle(WorkshopTicket $ticket, User $actor): WorkshopTicket
    {
        $this->guardStatus($ticket, WorkshopStatus::Completed);

        $ticket = $this->transition(
            $ticket,
            $actor,
            WorkshopStatus::Delivered,
            attributes: ['delivered_at' => now(), 'delivered_by' => $actor->id],
            afterCommit: fn () => WorkshopDelivered::dispatch($ticket->id, $actor->id),
        );

        $document = GeneratedDocument::query()
            ->where('document_type', DocumentType::WorkshopCompletionReport->value)
            ->where('related_type', WorkshopTicket::class)
            ->where('related_id', $ticket->id)
            ->latest('id')
            ->first()
            ?? $this->documents->generate(DocumentType::WorkshopCompletionReport, $ticket, $actor);

        $this->emailCustomer($ticket, $document->disk, $document->path);

        return $ticket->refresh();
    }

    private function emailCustomer(WorkshopTicket $ticket, string $disk, string $path): void
    {
        $ticket->loadMissing('device.customer');
        $email = $ticket->device?->customer?->email;

        if ($email === null || $email === '') {
            return;
        }

        $device = trim($ticket->device->brand.' '.$ticket->device->model);

        Notification::route('mail', $email)->notify(new WorkshopDeliveredNotification(
            $ticket->id,
            $ticket->number,
            $device !== '' ? $device : 'device',
            $disk,
            $path,
        ));
    }
}

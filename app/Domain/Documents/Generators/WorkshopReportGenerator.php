<?php

declare(strict_types=1);

namespace App\Domain\Documents\Generators;

use App\Domain\Documents\Support\DocumentContext;
use App\Domain\Workshop\Models\WorkshopAttachment;
use App\Domain\Workshop\Models\WorkshopTicket;

/**
 * Shared content builder for the workshop completion and delivery reports. Both
 * share one branded template, differing only by variant (title + delivery block).
 */
abstract class WorkshopReportGenerator extends AbstractDocumentGenerator
{
    abstract protected function variant(): string;

    protected function view(): string
    {
        return 'documents.workshop-report';
    }

    protected function number(DocumentContext $context): ?string
    {
        return $this->ticket($context)->number;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(DocumentContext $context): array
    {
        return ['ticket' => $this->ticket($context)->public_id];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(DocumentContext $context): array
    {
        $ticket = $this->ticket($context);
        $ticket->loadMissing(['device.customer', 'assignee', 'job', 'attachments']);
        $settings = $context->settings;
        $fmt = fn ($ts) => $ts !== null ? $settings->formatDateTime($ts) : null;

        return [
            'variant' => $this->variant(),
            'ticket' => $ticket,
            'number' => $ticket->number,
            'date' => $settings->formatDateTime(now()),
            'device' => $ticket->device,
            'customer' => $ticket->device?->customer,
            'assignee' => $ticket->assignee,
            'issue' => $ticket->issue_description,
            'repair_notes' => $ticket->repair_notes,
            'received_at' => $fmt($ticket->received_at),
            'completed_at' => $fmt($ticket->completed_at),
            'delivered_at' => $fmt($ticket->delivered_at),
            'job_number' => $ticket->job?->number,
            'images' => $ticket->attachments
                ->map(fn (WorkshopAttachment $a): ?string => $this->fileDataUri($a->disk, $a->path))
                ->filter()->values()->all(),
        ];
    }

    protected function ticket(DocumentContext $context): WorkshopTicket
    {
        /** @var WorkshopTicket $ticket */
        $ticket = $context->related;

        return $ticket;
    }
}

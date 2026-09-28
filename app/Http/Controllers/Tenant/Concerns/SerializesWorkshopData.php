<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant\Concerns;

use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\WorkshopTicketPart;
use App\Domain\Workshop\Models\WorkshopAttachment;
use App\Domain\Workshop\Models\WorkshopStatusHistory;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;

trait SerializesWorkshopData
{
    /**
     * @return array<string, mixed>
     */
    protected function ticketRow(WorkshopTicket $ticket): array
    {
        return [
            'id' => $ticket->public_id,
            'number' => $ticket->number,
            'status' => $ticket->status->value,
            'device' => $ticket->device
                ? trim($ticket->device->brand.' '.$ticket->device->model) : null,
            'customer' => $ticket->device?->customer?->company_name,
            'assignee' => $ticket->assignee?->name,
            'received_at' => $ticket->received_at?->toIso8601String(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function ticketDetail(WorkshopTicket $ticket, User $viewer): array
    {
        return [
            ...$this->ticketRow($ticket),
            'issue_description' => $ticket->issue_description,
            'repair_notes' => $ticket->repair_notes,
            'completed_at' => $ticket->completed_at?->toIso8601String(),
            'delivered_at' => $ticket->delivered_at?->toIso8601String(),
            'device_detail' => $ticket->device ? [
                'brand' => $ticket->device->brand,
                'model' => $ticket->device->model,
                'serial' => $ticket->device->serial_number,
                'serial_unavailable' => $ticket->device->serial_number_unavailable,
            ] : null,
            'job' => $ticket->job ? ['id' => $ticket->job->public_id, 'number' => $ticket->job->number] : null,
            'attachments' => $ticket->attachments->map(fn (WorkshopAttachment $a): array => [
                'id' => $a->public_id,
                'kind' => $a->kind,
                'url' => route('tenant.workshop.attachments.download', $a->public_id),
                'thumb_url' => route('tenant.workshop.attachments.download', ['attachment' => $a->public_id, 'thumb' => 1]),
            ])->all(),
            'history' => $ticket->statusHistory->map(fn (WorkshopStatusHistory $h): array => [
                'id' => $h->public_id,
                'from' => $h->from_status?->value,
                'to' => $h->to_status->value,
                'actor' => $h->actor?->name,
                'created_at' => $h->created_at?->toIso8601String(),
            ])->all(),
            'documents' => GeneratedDocument::query()
                ->where('related_type', WorkshopTicket::class)
                ->where('related_id', $ticket->id)
                ->latest('id')->get()
                ->map(fn (GeneratedDocument $d): array => [
                    'id' => $d->public_id,
                    'type' => $d->document_type->label(),
                    'number' => $d->number,
                    'url' => $d->temporaryDownloadUrl(),
                ])->all(),
            'can_view_cost' => $viewer->can('viewCost', InventoryItem::class),
            'parts' => WorkshopTicketPart::query()
                ->with('item')
                ->where('workshop_ticket_id', $ticket->id)
                ->latest('id')->get()
                ->map(fn (WorkshopTicketPart $p): array => [
                    'id' => $p->public_id,
                    'item' => $p->item?->name,
                    'sku' => $p->item?->sku,
                    'quantity' => (float) $p->quantity,
                    'sale_price' => $p->sale_price_snapshot,
                    'unit_cost' => $viewer->can('viewCost', InventoryItem::class) ? $p->unit_cost_snapshot : null,
                ])->all(),
            'abilities' => [
                'update' => $viewer->can('update', $ticket),
                'complete' => $viewer->can('complete', $ticket),
                'deliver' => $viewer->can('deliver', $ticket),
            ],
        ];
    }
}

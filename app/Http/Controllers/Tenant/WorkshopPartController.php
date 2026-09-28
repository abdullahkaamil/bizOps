<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Inventory\Actions\AddPartToWorkshopTicketAction;
use App\Domain\Inventory\Actions\RemovePartFromWorkshopTicketAction;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\WorkshopTicketPart;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AddWorkshopPartRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class WorkshopPartController extends Controller
{
    public function store(AddWorkshopPartRequest $request, WorkshopTicket $ticket, AddPartToWorkshopTicketAction $action): RedirectResponse
    {
        // Adding parts is part of working the ticket.
        $this->authorize('update', $ticket);

        $item = InventoryItem::where('public_id', $request->validated('inventory_item_id'))->firstOrFail();

        $action->handle($ticket, $item, (float) $request->validated('quantity'), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Part added.')]);

        return back();
    }

    public function destroy(WorkshopTicketPart $part, RemovePartFromWorkshopTicketAction $action): RedirectResponse
    {
        $part->load('ticket');
        $this->authorize('update', $part->ticket);

        $action->handle($part, request()->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Part returned.')]);

        return back();
    }
}

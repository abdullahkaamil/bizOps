<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Workshop\Actions\CreateWorkshopTicketAction;
use App\Domain\Workshop\Actions\UpdateRepairNotesAction;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\SerializesWorkshopData;
use App\Http\Requests\Tenant\StoreWorkshopTicketRequest;
use App\Http\Requests\Tenant\UpdateRepairNotesRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class WorkshopController extends Controller
{
    use SerializesWorkshopData;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', WorkshopTicket::class);

        $tickets = WorkshopTicket::query()
            ->with(['device.customer', 'assignee'])
            ->orderByDesc('id')
            ->get()
            ->groupBy(fn (WorkshopTicket $t): string => $t->status->value)
            ->map(fn ($group) => $group->map(fn (WorkshopTicket $t): array => $this->ticketRow($t))->values());

        return Inertia::render('workshop/Index', [
            'inProgress' => $tickets->get(WorkshopStatus::InProgress->value, collect())->all(),
            'completed' => $tickets->get(WorkshopStatus::Completed->value, collect())->all(),
            'delivered' => $tickets->get(WorkshopStatus::Delivered->value, collect())->all(),
            'canCreate' => $request->user()->can('create', WorkshopTicket::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', WorkshopTicket::class);

        return Inertia::render('workshop/Create', [
            'customers' => Customer::query()->orderBy('company_name')->get()
                ->map(fn (Customer $c): array => ['id' => $c->public_id, 'name' => $c->company_name])->all(),
            'technicians' => User::query()->where('user_type', 'internal')->where('status', 'active')
                ->orderBy('name')->get(['public_id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->public_id, 'name' => $u->name])->all(),
            'activeJobs' => Job::query()
                ->with('customer:id,public_id')
                ->whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value])
                ->latest('id')->get(['id', 'public_id', 'number', 'title', 'customer_id'])
                ->map(fn (Job $job): array => [
                    'id' => $job->public_id,
                    'customer_id' => $job->customer?->public_id,
                    'label' => $job->number.' — '.$job->title,
                ])->all(),
        ]);
    }

    /**
     * Serial lookup for intake. Searches the current tenant only and warns when a
     * serial is registered to a different customer (never auto-reassigns).
     */
    public function deviceLookup(Request $request): JsonResponse
    {
        $this->authorize('create', WorkshopTicket::class);

        $customer = Customer::where('public_id', $request->string('customer')->toString())->first();
        $serial = trim($request->string('serial')->toString());

        if ($serial === '') {
            return response()->json(['device' => null, 'history' => [], 'warning' => null]);
        }

        $device = CustomerDevice::where('serial_number', $serial)->first();

        if ($device === null) {
            return response()->json(['device' => null, 'history' => [], 'warning' => null]);
        }

        $belongsToCurrent = $customer !== null && $device->customer_id === $customer->id;

        return response()->json([
            'device' => [
                'id' => $device->public_id,
                'brand' => $device->brand,
                'model' => $device->model,
                'serial' => $device->serial_number,
                'belongs_to_current_customer' => $belongsToCurrent,
            ],
            'history' => $belongsToCurrent
                ? $device->tickets()->get()->map(fn (WorkshopTicket $t): array => [
                    'number' => $t->number,
                    'status' => $t->status->value,
                    'received_at' => $t->received_at?->toIso8601String(),
                ])->all()
                : [],
            'warning' => $belongsToCurrent ? null : 'This serial number is registered to another customer.',
        ]);
    }

    public function store(StoreWorkshopTicketRequest $request, CreateWorkshopTicketAction $action): RedirectResponse
    {
        $this->authorize('create', WorkshopTicket::class);

        $ticket = $action->handle($this->resolveAttributes($request->validated()), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Ticket created.')]);

        return to_route('tenant.workshop.show', $ticket);
    }

    public function show(Request $request, WorkshopTicket $ticket): Response
    {
        $this->authorize('view', $ticket);

        $ticket->load([
            'device.customer', 'assignee', 'job', 'attachments', 'statusHistory.actor',
        ]);

        return Inertia::render('workshop/Show', [
            'ticket' => $this->ticketDetail($ticket, $request->user()),
            'inventoryOptions' => InventoryItem::query()
                ->where('status', 'active')->orderBy('name')->get(['public_id', 'sku', 'name'])
                ->map(fn ($i): array => ['id' => $i->public_id, 'label' => $i->sku.' — '.$i->name])->all(),
        ]);
    }

    public function updateRepairNotes(UpdateRepairNotesRequest $request, WorkshopTicket $ticket, UpdateRepairNotesAction $action): RedirectResponse
    {
        $this->authorize('update', $ticket);

        $action->handle($ticket, $request->user(), $request->validated('repair_notes'));

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Repair notes saved.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function resolveAttributes(array $validated): array
    {
        return [
            'customer_id' => Customer::where('public_id', $validated['customer_id'])->value('id'),
            'device_id' => ! empty($validated['device_id'])
                ? CustomerDevice::where('public_id', $validated['device_id'])->value('id') : null,
            'serial_number' => $validated['serial_number'] ?? null,
            'serial_number_unavailable' => $validated['serial_number_unavailable'] ?? false,
            'brand' => $validated['brand'] ?? null,
            'model' => $validated['model'] ?? null,
            'issue_description' => $validated['issue_description'],
            'assigned_user_id' => ! empty($validated['assigned_user_id'])
                ? User::where('public_id', $validated['assigned_user_id'])->value('id') : null,
            'job_id' => ! empty($validated['job_id'])
                ? Job::where('public_id', $validated['job_id'])->value('id') : null,
        ];
    }
}

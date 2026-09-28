<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Enums\AddressType;
use App\Domain\CRM\Enums\CustomerStatus;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Tasks\Enums\TaskStatus;
use App\Domain\Tasks\Models\Board;
use App\Domain\Tasks\Models\Task;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreCustomerRequest;
use App\Support\Money;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class CustomerController extends Controller
{
    /**
     * Paginated, searchable, filterable customer list.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Customer::class);

        $search = trim($request->string('search')->toString());
        $status = $request->string('status')->toString() ?: null;

        $customers = Customer::query()
            ->status($status)
            ->when($search !== '', fn (Builder $query) => $query->where(function (Builder $query) use ($search): void {
                $query->where('company_name', 'ilike', "%{$search}%")
                    ->orWhere('email', 'ilike', "%{$search}%")
                    ->orWhere('phone', 'ilike', "%{$search}%")
                    ->orWhereHas('contacts', fn (Builder $c) => $c
                        ->where('first_name', 'ilike', "%{$search}%")
                        ->orWhere('last_name', 'ilike', "%{$search}%")
                        ->orWhere('email', 'ilike', "%{$search}%"));
            }))
            ->withCount(['contacts', 'addresses'])
            ->orderBy('company_name')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Customer $customer): array => [
                'id' => $customer->public_id,
                'company_name' => $customer->company_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status->value,
                'contacts_count' => $customer->contacts_count,
                'addresses_count' => $customer->addresses_count,
            ]);

        return Inertia::render('customers/Index', [
            'customers' => $customers,
            'filters' => ['search' => $search, 'status' => $status],
            'statuses' => CustomerStatus::values(),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Customer::class);

        return Inertia::render('customers/Create', [
            'statuses' => CustomerStatus::values(),
        ]);
    }

    public function store(StoreCustomerRequest $request): RedirectResponse
    {
        $this->authorize('create', Customer::class);

        $customer = Customer::create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer created.')]);

        return to_route('tenant.customers.show', $customer);
    }

    /**
     * Customer 360 profile. The initial request loads only the summary, primary
     * contact/address, and small counts. Each history tab is an Inertia optional
     * prop — only resolved (and paginated) on a partial reload — so opening a
     * profile never loads every history at once.
     */
    public function show(Request $request, Customer $customer): Response
    {
        $this->authorize('view', $customer);

        $customer->load([
            'contacts' => fn ($q) => $q->orderByDesc('is_primary')->orderBy('last_name'),
            'addresses' => fn ($q) => $q->orderByDesc('is_primary'),
        ]);

        return Inertia::render('customers/Show', [
            'customer' => [
                'id' => $customer->public_id,
                'company_name' => $customer->company_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status->value,
                'tax_number' => $customer->tax_number,
                'notes' => $customer->notes,
            ],
            'contacts' => $customer->contacts->map(fn ($c) => $this->contactRow($c))->all(),
            'addresses' => $customer->addresses->map(fn ($a) => $this->addressRow($a))->all(),
            'addressTypes' => AddressType::values(),

            // Small counts loaded up-front for the header badges.
            'counts' => $this->customerCounts($customer),

            // Lazy, paginated tabs — each resolved only when its tab is opened.
            'jobs' => Inertia::optional(fn () => $this->jobsTab($customer)),
            'devices' => Inertia::optional(fn () => $this->devicesTab($customer)),
            'workshop' => Inertia::optional(fn () => $this->workshopTab($customer)),
            'quotations' => Inertia::optional(fn () => $this->quotationsTab($customer)),
            'boards' => Inertia::optional(fn () => $this->boardsTab($customer)),
            'documents' => Inertia::optional(fn () => $this->documentsTab($customer)),
            'activities' => Inertia::optional(fn () => $this->activityTab($customer)),
        ]);
    }

    /**
     * @return array<string, int>
     */
    protected function customerCounts(Customer $customer): array
    {
        return [
            'open_jobs' => Job::where('customer_id', $customer->id)
                ->whereIn('status', [JobStatus::Pending->value, JobStatus::InProgress->value])->count(),
            'active_workshop' => WorkshopTicket::whereHas('device', fn (Builder $q) => $q->where('customer_id', $customer->id))
                ->where('status', '!=', WorkshopStatus::Delivered->value)->count(),
            'devices' => CustomerDevice::where('customer_id', $customer->id)->count(),
            'draft_quotations' => Quotation::where('customer_id', $customer->id)
                ->where('status', QuotationStatus::Draft->value)->count(),
            'open_tasks' => Task::whereHas('board', fn (Builder $q) => $q->where('customer_id', $customer->id)->where('type', 'project'))
                ->where('status', '!=', TaskStatus::Completed->value)->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function jobsTab(Customer $customer): LengthAwarePaginator
    {
        return Job::where('customer_id', $customer->id)
            ->with('assignee')->latest('id')
            ->paginate(10, ['*'], 'jobs_page')->withQueryString()
            ->through(fn (Job $j): array => $this->jobRow($j));
    }

    /**
     * @return array<string, mixed>
     */
    protected function jobRow(Job $j): array
    {
        return [
            'id' => $j->public_id,
            'number' => $j->number,
            'title' => $j->title,
            'status' => $j->status->value,
            'assignee' => $j->assignee?->name,
            'planned_at' => $j->planned_at?->toIso8601String(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function devicesTab(Customer $customer): LengthAwarePaginator
    {
        return CustomerDevice::where('customer_id', $customer->id)
            ->withCount('tickets')->latest('id')
            ->paginate(10, ['*'], 'devices_page')->withQueryString()
            ->through(fn (CustomerDevice $d): array => $this->deviceRow($d));
    }

    /**
     * @return array<string, mixed>
     */
    protected function deviceRow(CustomerDevice $d): array
    {
        return [
            'id' => $d->public_id,
            'brand' => $d->brand,
            'model' => $d->model,
            'serial' => $d->serial_number,
            'tickets_count' => $d->tickets_count,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function workshopTab(Customer $customer): LengthAwarePaginator
    {
        return WorkshopTicket::whereHas('device', fn (Builder $q) => $q->where('customer_id', $customer->id))
            ->with('device')->latest('id')
            ->paginate(10, ['*'], 'workshop_page')->withQueryString()
            ->through(fn (WorkshopTicket $t): array => $this->workshopRow($t));
    }

    /**
     * @return array<string, mixed>
     */
    protected function workshopRow(WorkshopTicket $t): array
    {
        return [
            'id' => $t->public_id,
            'number' => $t->number,
            'device' => $t->device ? trim($t->device->brand.' '.$t->device->model) : null,
            'status' => $t->status->value,
            'received_at' => $t->received_at?->toIso8601String(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function quotationsTab(Customer $customer): LengthAwarePaginator
    {
        return Quotation::where('customer_id', $customer->id)->latest('id')
            ->paginate(10, ['*'], 'quotations_page')->withQueryString()
            ->through(fn (Quotation $q): array => $this->quotationRow($q));
    }

    /**
     * @return array<string, mixed>
     */
    protected function quotationRow(Quotation $q): array
    {
        return [
            'id' => $q->public_id,
            'number' => $q->number,
            'status' => $q->status->value,
            'currency' => $q->currency,
            'grand_total' => Money::toMajor($q->grand_total),
            'valid_until' => $q->valid_until?->toDateString(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function boardsTab(Customer $customer): LengthAwarePaginator
    {
        return Board::where('customer_id', $customer->id)->where('type', 'project')
            ->withCount('tasks')->latest('id')
            ->paginate(10, ['*'], 'boards_page')->withQueryString()
            ->through(fn (Board $b): array => $this->boardRow($b));
    }

    /**
     * @return array<string, mixed>
     */
    protected function boardRow(Board $b): array
    {
        return [
            'id' => $b->public_id,
            'name' => $b->name,
            'tasks_count' => $b->tasks_count,
            'is_active' => $b->is_active,
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function documentsTab(Customer $customer): LengthAwarePaginator
    {
        $jobIds = Job::where('customer_id', $customer->id)->pluck('id');
        $ticketIds = WorkshopTicket::whereHas('device', fn (Builder $q) => $q->where('customer_id', $customer->id))->pluck('id');
        $quoteIds = Quotation::where('customer_id', $customer->id)->pluck('id');

        return GeneratedDocument::query()
            ->where(function (Builder $q) use ($jobIds, $ticketIds, $quoteIds): void {
                $q->where(fn (Builder $w) => $w->where('related_type', Job::class)->whereIn('related_id', $jobIds))
                    ->orWhere(fn (Builder $w) => $w->where('related_type', WorkshopTicket::class)->whereIn('related_id', $ticketIds))
                    ->orWhere(fn (Builder $w) => $w->where('related_type', Quotation::class)->whereIn('related_id', $quoteIds));
            })
            ->latest('id')
            ->paginate(10, ['*'], 'documents_page')->withQueryString()
            ->through(fn (GeneratedDocument $d): array => $this->documentRow($d));
    }

    /**
     * @return array<string, mixed>
     */
    protected function documentRow(GeneratedDocument $d): array
    {
        return [
            'id' => $d->public_id,
            'type' => $d->document_type->label(),
            'number' => $d->number,
            'generated_at' => $d->generated_at?->toIso8601String(),
            // Signed, temporary, policy-checked on download.
            'url' => $d->temporaryDownloadUrl(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    protected function activityTab(Customer $customer): LengthAwarePaginator
    {
        return Activity::query()
            ->where('subject_type', Customer::class)
            ->where('subject_id', $customer->id)
            ->latest()
            ->paginate(15, ['*'], 'activity_page')->withQueryString()
            ->through(fn (Activity $a): array => $this->activityRow($a));
    }

    /**
     * @return array<string, mixed>
     */
    protected function activityRow(Activity $a): array
    {
        return [
            'description' => $a->description,
            'log_name' => $a->log_name,
            'created_at' => $a->created_at?->toIso8601String(),
        ];
    }

    public function edit(Customer $customer): Response
    {
        $this->authorize('update', $customer);

        return Inertia::render('customers/Edit', [
            'customer' => [
                'id' => $customer->public_id,
                'company_name' => $customer->company_name,
                'email' => $customer->email,
                'phone' => $customer->phone,
                'status' => $customer->status->value,
                'tax_number' => $customer->tax_number,
                'notes' => $customer->notes,
            ],
            'statuses' => CustomerStatus::values(),
        ]);
    }

    public function update(StoreCustomerRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $customer->update([
            ...$request->validated(),
            'updated_by' => $request->user()->id,
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer updated.')]);

        return to_route('tenant.customers.show', $customer);
    }

    public function destroy(Customer $customer): RedirectResponse
    {
        $this->authorize('delete', $customer);

        $customer->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Customer deleted.')]);

        return to_route('tenant.customers.index');
    }

    /**
     * @return array<string, mixed>
     */
    protected function contactRow(CustomerContact $contact): array
    {
        return [
            'id' => $contact->public_id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'job_title' => $contact->job_title,
            'is_primary' => $contact->is_primary,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function addressRow(CustomerAddress $address): array
    {
        return [
            'id' => $address->public_id,
            'type' => $address->type->value,
            'label' => $address->label,
            'address_line_1' => $address->address_line_1,
            'address_line_2' => $address->address_line_2,
            'city' => $address->city,
            'state' => $address->state,
            'postal_code' => $address->postal_code,
            'country_code' => $address->country_code,
            'is_primary' => $address->is_primary,
        ];
    }
}

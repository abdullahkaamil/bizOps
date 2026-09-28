<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Actions\UpdateJobServiceDataAction;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Settings\TenantSettings;
use App\Enums\Permission;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\Concerns\SerializesJobData;
use App\Http\Requests\Tenant\StoreJobRequest;
use App\Http\Requests\Tenant\UpdateJobRequest;
use App\Http\Requests\Tenant\UpdateJobServiceDataRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class JobController extends Controller
{
    use SerializesJobData;

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Job::class);

        $viewer = $request->user();
        $status = $request->string('status')->toString() ?: null;
        $customerPublicId = $request->string('customer')->toString() ?: null;
        $customerId = $customerPublicId !== null
            ? Customer::where('public_id', $customerPublicId)->value('id')
            : null;

        $jobs = Job::query()
            ->with(['customer', 'assignee'])
            // Technicians see their own jobs; dispatchers/managers see all.
            ->when(! $viewer->can(Permission::AssignJobs->value),
                fn (Builder $q) => $q->where('assigned_user_id', $viewer->id))
            ->when($status !== null, fn (Builder $q) => $q->where('status', $status))
            ->when($customerPublicId !== null, fn (Builder $q) => $q->where('customer_id', $customerId ?? 0))
            ->orderByRaw("CASE status WHEN 'in_progress' THEN 0 WHEN 'pending' THEN 1 ELSE 2 END")
            ->orderBy('planned_at')
            ->get()
            ->map(fn (Job $job): array => $this->jobRow($job))
            ->all();

        return Inertia::render('jobs/Index', [
            'jobs' => $jobs,
            'filters' => ['status' => $status, 'customer' => $customerPublicId],
            'statuses' => JobStatus::values(),
            'customers' => Customer::query()
                ->orderBy('company_name')
                ->get(['public_id', 'company_name'])
                ->map(fn (Customer $customer): array => [
                    'id' => $customer->public_id,
                    'name' => $customer->company_name,
                ])->all(),
            'canCreate' => $viewer->can('create', Job::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', Job::class);

        return Inertia::render('jobs/Create', $this->formData());
    }

    public function store(StoreJobRequest $request, CreateJobAction $action): RedirectResponse
    {
        $this->authorize('create', Job::class);

        $job = $action->handle($this->resolveAttributes($request->validated()), $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job created.')]);

        return to_route('tenant.jobs.show', $job);
    }

    public function show(Request $request, Job $job): Response
    {
        $this->authorize('view', $job);

        $job->load([
            'customer', 'contact', 'serviceAddress', 'assignee',
            'images', 'signatures', 'statusHistory.actor',
        ]);

        $settings = app(TenantSettings::class);

        return Inertia::render('jobs/Show', [
            'job' => $this->jobDetail($job, $request->user()),
            'requirements' => [
                'signature' => $settings->jobsRequireSignature(),
                'photo' => $settings->jobsRequirePhoto(),
                'min_service_notes' => $settings->jobsMinServiceNotes(),
            ],
        ]);
    }

    public function update(UpdateJobRequest $request, Job $job): RedirectResponse
    {
        $this->authorize('update', $job);

        $data = $request->validated();

        $job->update([
            'title' => $data['title'],
            'description' => $data['description'] ?? null,
            'planned_at' => $data['planned_at'] ?? null,
            'customer_contact_id' => $this->idFrom(CustomerContact::class, $data['customer_contact_id'] ?? null),
            'service_address_id' => $this->idFrom(CustomerAddress::class, $data['service_address_id'] ?? null),
        ]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Job updated.')]);

        return back();
    }

    public function updateServiceData(UpdateJobServiceDataRequest $request, Job $job, UpdateJobServiceDataAction $action): RedirectResponse
    {
        $this->authorize('updateServiceData', $job);

        $action->handle($job, $request->user(), $request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Notes saved.')]);

        return back();
    }

    /**
     * Generate a branded, private service-report PDF for the job.
     */
    public function generateReport(Request $request, Job $job, DocumentService $documents): RedirectResponse
    {
        $this->authorize('generateReport', $job);

        $documents->generate(DocumentType::JobServiceReport, $job, $request->user());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Service report generated.')]);

        return back();
    }

    /**
     * @param  array<string, mixed>  $validated
     * @return array<string, mixed>
     */
    protected function resolveAttributes(array $validated): array
    {
        return [
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'planned_at' => $validated['planned_at'] ?? null,
            'customer_id' => $this->idFrom(Customer::class, $validated['customer_id'] ?? null),
            'customer_contact_id' => $this->idFrom(CustomerContact::class, $validated['customer_contact_id'] ?? null),
            'service_address_id' => $this->idFrom(CustomerAddress::class, $validated['service_address_id'] ?? null),
            'assigned_user_id' => $this->idFrom(User::class, $validated['assigned_user_id'] ?? null),
        ];
    }

    /**
     * @param  class-string<Model>  $model
     */
    protected function idFrom(string $model, ?string $publicId): ?int
    {
        if ($publicId === null || $publicId === '') {
            return null;
        }

        return $model::where('public_id', $publicId)->value('id');
    }

    /**
     * @return array<string, mixed>
     */
    protected function formData(): array
    {
        return [
            'customers' => Customer::query()
                ->with([
                    'contacts' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('last_name'),
                    'addresses' => fn ($query) => $query->orderByDesc('is_primary')->orderBy('city'),
                ])
                ->orderBy('company_name')
                ->get()
                ->map(fn (Customer $c): array => [
                    'id' => $c->public_id,
                    'name' => $c->company_name,
                    'email' => $c->email,
                    'phone' => $c->phone,
                    'tax_number' => $c->tax_number,
                    'contacts' => $c->contacts
                        ->map(fn (CustomerContact $ct): array => [
                            'id' => $ct->public_id,
                            'name' => trim($ct->first_name.' '.$ct->last_name),
                            'email' => $ct->email,
                            'phone' => $ct->phone,
                        ])->all(),
                    'addresses' => $c->addresses
                        ->map(fn (CustomerAddress $a): array => [
                            'id' => $a->public_id,
                            'label' => collect([
                                $a->address_line_1,
                                $a->address_line_2,
                                trim(($a->postal_code ?? '').' '.$a->city),
                                $a->country_code,
                            ])->filter()->implode(', '),
                        ])->all(),
                ])->all(),
            'technicians' => User::query()->where('user_type', 'internal')->where('status', 'active')
                ->orderBy('name')->get(['public_id', 'name'])
                ->map(fn (User $u): array => ['id' => $u->public_id, 'name' => $u->name])->all(),
        ];
    }
}

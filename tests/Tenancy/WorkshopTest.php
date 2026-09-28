<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Jobs\Actions\CompleteJobAction;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Actions\StartJobAction;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Notifications\Enums\EmailStatus;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Workshop\Actions\CompleteWorkshopTicketAction;
use App\Domain\Workshop\Actions\CreateWorkshopTicketAction;
use App\Domain\Workshop\Actions\DeliverWorkshopTicketAction;
use App\Domain\Workshop\Actions\LinkWorkshopTicketToJobAction;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Exceptions\InvalidWorkshopTransition;
use App\Domain\Workshop\Models\CustomerDevice;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;

function wsTenant(string $slug = 'alpha'): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Alpha',
        'subdomain' => $slug,
        'admin_name' => ucfirst($slug).' Owner',
        'admin_email' => "owner@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

/**
 * @return array<string, mixed>
 */
function wsActors(): array
{
    $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active', 'email' => 'client@x.test']);
    $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
    $tech->assignRole(Role::Technician->value);

    return compact('customer', 'tech');
}

test('an existing serial reuses the device; a new serial creates one', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();

        $first = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'SN-100',
            'brand' => 'Acme', 'model' => 'X1', 'issue_description' => 'No power',
        ], $tech);

        $second = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'SN-100',
            'brand' => 'Acme', 'model' => 'X1', 'issue_description' => 'Again',
        ], $tech);

        // Same serial => same device, not a duplicate.
        expect($first->device_id)->toBe($second->device_id)
            ->and(CustomerDevice::count())->toBe(1);

        // A different serial creates a new device.
        app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'SN-200',
            'brand' => 'Acme', 'model' => 'X2', 'issue_description' => 'Broken',
        ], $tech);

        expect(CustomerDevice::count())->toBe(2);
    });
});

test('a device with no serial is accepted with the unavailable flag', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();

        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number_unavailable' => true,
            'brand' => 'Acme', 'model' => 'X3', 'issue_description' => 'No serial',
        ], $tech);

        expect($ticket->device->serial_number)->toBeNull()
            ->and($ticket->device->serial_number_unavailable)->toBeTrue();
    });
});

test('a duplicate real serial is blocked by the unique index', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer] = wsActors();

        CustomerDevice::create(['customer_id' => $customer->id, 'serial_number' => 'DUP-1', 'brand' => 'A', 'model' => 'B']);

        expect(fn () => CustomerDevice::create([
            'customer_id' => $customer->id, 'serial_number' => 'DUP-1', 'brand' => 'A', 'model' => 'B',
        ]))->toThrow(QueryException::class);
    });
});

test('a serial belonging to another customer is not silently reassigned', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customerA, 'tech' => $tech] = wsActors();
        $customerB = Customer::create(['company_name' => 'Other', 'status' => 'active']);

        app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customerA->id, 'serial_number' => 'SHARED',
            'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);

        // Intake for customer B with the same serial is rejected.
        expect(fn () => app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customerB->id, 'serial_number' => 'SHARED',
            'brand' => 'A', 'model' => 'B', 'issue_description' => 'y',
        ], $tech))->toThrow(ValidationException::class);

        // The device still belongs to customer A.
        expect(CustomerDevice::where('serial_number', 'SHARED')->value('customer_id'))->toBe($customerA->id);
    });
});

test('completion requires repair notes and records the server time', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B',
            'issue_description' => 'x', 'assigned_user_id' => $tech->id,
        ], $tech);

        // Without repair notes, completion fails.
        expect(fn () => app(CompleteWorkshopTicketAction::class)->handle($ticket, $tech))
            ->toThrow(ValidationException::class);

        app(CompleteWorkshopTicketAction::class)->handle($ticket, $tech, 'Replaced the board.');
        $ticket->refresh();

        expect($ticket->status)->toBe(WorkshopStatus::Completed)
            ->and($ticket->completed_at)->not->toBeNull()
            ->and($ticket->repair_notes)->toBe('Replaced the board.')
            ->and(GeneratedDocument::where('document_type', DocumentType::WorkshopCompletionReport->value)->count())->toBe(1);
    });
});

test('delivery requires a completed ticket', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);

        // Still in progress -> cannot deliver.
        expect(fn () => app(DeliverWorkshopTicketAction::class)->handle($ticket, $tech))
            ->toThrow(InvalidWorkshopTransition::class);
    });
});

test('delivery reuses the completion PDF and queues the customer email', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'Acme', 'model' => 'X1', 'issue_description' => 'x',
        ], $tech);
        app(CompleteWorkshopTicketAction::class)->handle($ticket, $tech, 'Fixed.');

        app(DeliverWorkshopTicketAction::class)->handle($ticket->refresh(), $tech);
        $ticket->refresh();

        expect($ticket->status)->toBe(WorkshopStatus::Delivered)
            ->and($ticket->delivered_at)->not->toBeNull()
            ->and(GeneratedDocument::count())->toBe(1)
            ->and(GeneratedDocument::where('document_type', DocumentType::WorkshopCompletionReport->value)->count())->toBe(1)
            ->and(GeneratedDocument::where('document_type', DocumentType::WorkshopDeliveryReport->value)->count())->toBe(0);

        // The delivery email to the customer was sent (QUEUE=sync) and logged.
        $log = EmailLog::where('notification_type', NotificationType::WorkshopDelivered->value)->first();
        expect($log)->not->toBeNull()
            ->and($log->recipient)->toBe('client@x.test')
            ->and($log->status)->toBe(EmailStatus::Sent);
    });
});

test('workshop intake lists only active jobs and identifies their customer', function () {
    $tenant = wsTenant();
    [$owner, $customer, $pendingJob, $completedJob] = $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();
        $owner = User::where('email', 'owner@alpha.test')->firstOrFail();
        $pendingJob = app(CreateJobAction::class)->handle([
            'title' => 'Pending service', 'customer_id' => $customer->id,
        ], $owner);
        $completedJob = app(CreateJobAction::class)->handle([
            'title' => 'Completed service', 'customer_id' => $customer->id, 'assigned_user_id' => $tech->id,
        ], $owner);
        app(StartJobAction::class)->handle($completedJob, $tech);
        app(CompleteJobAction::class)->handle($completedJob->fresh(), $tech);

        return [$owner, $customer, $pendingJob, $completedJob];
    });

    expect($tenant->run(fn () => $completedJob->fresh()->status))->toBe(JobStatus::Completed);

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/workshop/create')
        ->assertInertia(fn ($page) => $page
            ->has('activeJobs', 1)
            ->where('activeJobs.0.id', $pendingJob->public_id)
            ->where('activeJobs.0.customer_id', $customer->public_id));
});

test('workshop intake rejects completed and other-customer jobs', function () {
    $tenant = wsTenant();
    [$owner, $customer, $completedJob, $otherJob] = $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();
        $owner = User::where('email', 'owner@alpha.test')->firstOrFail();
        $completedJob = app(CreateJobAction::class)->handle([
            'title' => 'Completed', 'customer_id' => $customer->id, 'assigned_user_id' => $tech->id,
        ], $owner);
        app(StartJobAction::class)->handle($completedJob, $tech);
        app(CompleteJobAction::class)->handle($completedJob->fresh(), $tech);

        $otherCustomer = Customer::create(['company_name' => 'Other Co', 'status' => 'active']);
        $otherJob = app(CreateJobAction::class)->handle([
            'title' => 'Other customer', 'customer_id' => $otherCustomer->id,
        ], $owner);

        return [$owner, $customer, $completedJob, $otherJob];
    });

    $payload = [
        'customer_id' => $customer->public_id,
        'serial_number' => 'INTAKE-1',
        'brand' => 'Acme',
        'model' => 'X1',
        'issue_description' => 'No power',
    ];

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/workshop', [...$payload, 'job_id' => $completedJob->public_id])
        ->assertSessionHasErrors('job_id');

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/workshop', [...$payload, 'job_id' => $otherJob->public_id])
        ->assertSessionHasErrors('job_id');
});

test('workshop completion and delivery abilities follow ticket status', function () {
    $tenant = wsTenant();
    [$owner, $tech, $ticket] = $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();
        $owner = User::where('email', 'owner@alpha.test')->firstOrFail();
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id,
            'serial_number' => 'ABILITY-1',
            'brand' => 'Acme',
            'model' => 'X1',
            'issue_description' => 'No power',
        ], $owner);

        return [$owner, $tech, $ticket];
    });
    $url = "http://alpha.kaamil.test/workshop/{$ticket->public_id}";

    $this->actingAs($owner)->get($url)->assertInertia(fn ($page) => $page
        ->where('ticket.abilities.complete', true)
        ->where('ticket.abilities.deliver', false));

    $tenant->run(fn () => app(CompleteWorkshopTicketAction::class)->handle($ticket->fresh(), $tech, 'Fixed.'));

    $this->actingAs($owner)->get($url)->assertInertia(fn ($page) => $page
        ->where('ticket.abilities.complete', false)
        ->where('ticket.abilities.deliver', true));

    $tenant->run(fn () => app(DeliverWorkshopTicketAction::class)->handle($ticket->fresh(), $tech));

    $this->actingAs($owner)->get($url)->assertInertia(fn ($page) => $page
        ->where('ticket.abilities.complete', false)
        ->where('ticket.abilities.deliver', false));
});

test('a job linked to an open workshop ticket cannot be completed early', function () {
    $tenant = wsTenant();

    $tenant->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();

        $job = app(CreateJobAction::class)->handle([
            'title' => 'Install', 'customer_id' => $customer->id, 'assigned_user_id' => $tech->id,
            'service_notes' => 'done',
        ], $tech);

        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);
        app(LinkWorkshopTicketToJobAction::class)->handle($ticket, $job, $tech);

        app(StartJobAction::class)->handle($job->refresh(), $tech);

        // Workshop ticket still in progress -> job completion blocked.
        expect(fn () => app(CompleteJobAction::class)->handle($job->refresh(), $tech))
            ->toThrow(ValidationException::class);

        // Once the ticket is completed, the job can complete.
        app(CompleteWorkshopTicketAction::class)->handle($ticket->refresh(), $tech, 'Repaired.');
        app(CompleteJobAction::class)->handle($job->refresh(), $tech);

        expect($job->refresh()->status->value)->toBe('completed');
    });
});

test('workshop tickets are isolated per tenant', function () {
    $alpha = wsTenant('alpha');
    $beta = wsTenant('beta');

    $alphaTicket = $alpha->run(function () {
        ['customer' => $customer, 'tech' => $tech] = wsActors();

        return app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);
    });

    expect($beta->run(fn () => WorkshopTicket::where('public_id', $alphaTicket->public_id)->exists()))->toBeFalse()
        ->and($alpha->run(fn () => WorkshopTicket::count()))->toBe(1);
});

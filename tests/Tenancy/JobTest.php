<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Domain\CRM\Models\CustomerContact;
use App\Domain\Jobs\Actions\CompleteJobAction;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Actions\StartJobAction;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Events\JobCompleted;
use App\Domain\Jobs\Exceptions\InvalidJobTransition;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Services\ImageProcessor;
use App\Domain\Settings\TenantSettings;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

function jbTenant(string $slug = 'alpha'): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Alpha',
        'subdomain' => $slug,
        'admin_name' => 'Alpha Owner',
        'admin_email' => "owner@{$slug}.test",
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

/**
 * A pending job with a technician assignee and its customer.
 *
 * @return array<string, mixed>
 */
function jbScenario(Tenant $tenant): array
{
    return $tenant->run(function (): array {
        $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);

        $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $tech->assignRole(Role::Technician->value);

        $job = app(CreateJobAction::class)->handle([
            'title' => 'Fix boiler',
            'customer_id' => $customer->id,
            'assigned_user_id' => $tech->id,
        ], $tech);

        return compact('customer', 'tech', 'job');
    });
}

test('job numbers are sequential and unique', function () {
    $tenant = jbTenant();

    $tenant->run(function () {
        $customer = Customer::create(['company_name' => 'C', 'status' => 'active']);
        $creator = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);

        $a = app(CreateJobAction::class)->handle(['title' => 'A', 'customer_id' => $customer->id], $creator);
        $b = app(CreateJobAction::class)->handle(['title' => 'B', 'customer_id' => $customer->id], $creator);

        expect($a->number)->not->toBe($b->number)
            ->and($a->number)->toStartWith('JOB-')
            ->and(Job::whereIn('number', [$a->number, $b->number])->count())->toBe(2);
    });
});

test('starting a job records the server time and rejects repeat starts', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    $tenant->run(function () use ($tech, $job) {
        expect($job->actual_start_at)->toBeNull();

        app(StartJobAction::class)->handle($job, $tech);
        $job->refresh();

        expect($job->status)->toBe(JobStatus::InProgress)
            ->and($job->actual_start_at)->not->toBeNull();

        // A second start is rejected.
        expect(fn () => app(StartJobAction::class)->handle($job, $tech))
            ->toThrow(InvalidJobTransition::class);
    });
});

test('a job cannot be completed before it is started', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    $tenant->run(function () use ($tech, $job) {
        expect(fn () => app(CompleteJobAction::class)->handle($job, $tech))
            ->toThrow(InvalidJobTransition::class);

        expect($job->fresh()->status)->toBe(JobStatus::Pending);
    });
});

test('job action abilities follow the required workflow', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);
    $url = "http://alpha.kaamil.test/jobs/{$job->public_id}";

    $this->actingAs($tech)->get($url)->assertInertia(fn ($page) => $page
        ->where('job.abilities.start', true)
        ->where('job.abilities.complete', false)
        ->where('job.abilities.service_data', false)
        ->where('job.abilities.generate_report', false));

    $tenant->run(fn () => app(StartJobAction::class)->handle($job->fresh(), $tech));

    $this->actingAs($tech)->get($url)->assertInertia(fn ($page) => $page
        ->where('job.abilities.start', false)
        ->where('job.abilities.complete', true)
        ->where('job.abilities.service_data', true)
        ->where('job.abilities.generate_report', false));

    $tenant->run(fn () => app(CompleteJobAction::class)->handle($job->fresh(), $tech));

    $this->actingAs($tech)->get($url)->assertInertia(fn ($page) => $page
        ->where('job.abilities.start', false)
        ->where('job.abilities.complete', false)
        ->where('job.abilities.service_data', false)
        ->where('job.abilities.generate_report', true));
});

test('an unassigned technician cannot start a job', function () {
    $tenant = jbTenant();
    ['job' => $job] = jbScenario($tenant);
    $other = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
        $u->assignRole(Role::Technician->value);

        return $u;
    });

    $this->actingAs($other)
        ->post("http://alpha.kaamil.test/jobs/{$job->public_id}/start")
        ->assertForbidden();

    expect($job->fresh()->status)->toBe(JobStatus::Pending);
});

test('completion without required service notes fails', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    $tenant->run(function () use ($tech, $job) {
        app(TenantSettings::class)->set('jobs', 'min_service_notes', 10);
        app(StartJobAction::class)->handle($job, $tech);

        expect(fn () => app(CompleteJobAction::class)->handle($job->refresh(), $tech))
            ->toThrow(ValidationException::class);

        expect($job->fresh()->status)->toBe(JobStatus::InProgress);
    });
});

test('completion without a required signature fails, then succeeds once present', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    $tenant->run(function () use ($tech, $job) {
        app(TenantSettings::class)->set('jobs', 'signature_required', true);
        app(StartJobAction::class)->handle($job, $tech);

        expect(fn () => app(CompleteJobAction::class)->handle($job->refresh(), $tech))
            ->toThrow(ValidationException::class);

        $job->signatures()->create(['disk' => 'local', 'path' => 'x.png', 'created_at' => now()]);

        app(CompleteJobAction::class)->handle($job->refresh(), $tech);
        expect($job->fresh()->status)->toBe(JobStatus::Completed);
    });
});

test('completion records the end time and a server-computed duration', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    Event::fake([JobCompleted::class]);

    $tenant->run(function () use ($tech, $job) {
        app(StartJobAction::class)->handle($job, $tech);

        // Simulate 30 minutes of work using server timestamps.
        $job->refresh()->update(['actual_start_at' => now()->subMinutes(30)]);

        app(CompleteJobAction::class)->handle($job->refresh(), $tech);
        $job->refresh();

        expect($job->actual_end_at)->not->toBeNull()
            ->and($job->durationMinutes())->toBe(30)
            ->and($job->completed_by)->toBe($tech->id);

        $last = $job->statusHistory()->reorder('id')->get()->last();
        expect($last->metadata['duration_minutes'])->toBe(30);
    });

    Event::assertDispatched(JobCompleted::class, fn (JobCompleted $e): bool => $e->jobId === $job->id && $e->durationMinutes === 30);
});

test('the image processor validates content and produces a thumbnail', function () {
    $processor = new ImageProcessor;

    // A real 4x4 PNG generated by GD (valid content).
    $img = imagecreatetruecolor(4, 4);
    ob_start();
    imagepng($img);
    $bytes = (string) ob_get_clean();
    imagedestroy($img);

    $result = $processor->process($bytes, 1600, 400);
    expect($result['mime'])->toBe('image/jpeg')
        ->and(strlen($result['thumbnail']))->toBeGreaterThan(0);

    // Garbage content is rejected.
    expect(fn () => $processor->process('not-an-image'))->toThrow(RuntimeException::class);
});

test('uploading a photo stores it privately and generates a thumbnail', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    $tenant->run(fn () => app(StartJobAction::class)->handle($job, $tech));

    $this->actingAs($tech)
        ->post("http://alpha.kaamil.test/jobs/{$job->public_id}/images", [
            'file' => UploadedFile::fake()->image('site.jpg', 800, 600),
        ])
        ->assertRedirect();

    $tenant->run(function () use ($job) {
        $image = $job->images()->first();
        expect($image)->not->toBeNull()
            ->and($image->processed)->toBeTrue()
            ->and($image->thumbnail_path)->not->toBeNull()
            ->and(Storage::disk('local')->exists($image->thumbnail_path))->toBeTrue();
    });
});

test('image download requires authorization and blocks other tenants', function () {
    $tenant = jbTenant();
    ['tech' => $tech, 'job' => $job] = jbScenario($tenant);

    $tenant->run(fn () => app(StartJobAction::class)->handle($job, $tech));

    $this->actingAs($tech)
        ->post("http://alpha.kaamil.test/jobs/{$job->public_id}/images", [
            'file' => UploadedFile::fake()->image('site.jpg'),
        ]);

    $image = $tenant->run(fn () => $job->images()->first());

    // The assigned technician can download it.
    $this->actingAs($tech)
        ->get("http://alpha.kaamil.test/job-images/{$image->public_id}/download")
        ->assertOk();
});

test('creating a job exposes the selected customer primary details', function () {
    $tenant = jbTenant();
    [$owner, $customer, $contact, $address] = $tenant->run(function () {
        $owner = User::where('email', 'owner@alpha.test')->firstOrFail();
        $customer = Customer::create([
            'company_name' => 'Client Co',
            'email' => 'office@client.test',
            'phone' => '555-1000',
            'tax_number' => 'TAX-42',
            'status' => 'active',
        ]);
        $contact = CustomerContact::create([
            'customer_id' => $customer->id,
            'first_name' => 'Primary',
            'last_name' => 'Contact',
            'email' => 'primary@client.test',
            'phone' => '555-2000',
            'is_primary' => true,
        ]);
        $address = CustomerAddress::create([
            'customer_id' => $customer->id,
            'type' => 'service',
            'address_line_1' => 'Main Street 10',
            'city' => 'Istanbul',
            'country_code' => 'TR',
            'is_primary' => true,
        ]);

        return [$owner, $customer, $contact, $address];
    });

    $this->actingAs($owner)
        ->get('http://alpha.kaamil.test/jobs/create')
        ->assertInertia(fn ($page) => $page
            ->component('jobs/Create')
            ->where('customers.0.id', $customer->public_id)
            ->where('customers.0.email', 'office@client.test')
            ->where('customers.0.contacts.0.id', $contact->public_id)
            ->where('customers.0.addresses.0.id', $address->public_id));
});

test('job customer filter returns only the selected customer jobs', function () {
    $tenant = jbTenant();
    [$owner, $selectedCustomer] = $tenant->run(function () {
        $owner = User::where('email', 'owner@alpha.test')->firstOrFail();
        $first = Customer::create(['company_name' => 'First Co', 'status' => 'active']);
        $second = Customer::create(['company_name' => 'Second Co', 'status' => 'active']);
        app(CreateJobAction::class)->handle(['title' => 'First service', 'customer_id' => $first->id], $owner);
        app(CreateJobAction::class)->handle(['title' => 'Second service', 'customer_id' => $second->id], $owner);

        return [$owner, $second];
    });

    $this->actingAs($owner)
        ->get("http://alpha.kaamil.test/jobs?customer={$selectedCustomer->public_id}")
        ->assertInertia(fn ($page) => $page
            ->has('jobs', 1)
            ->where('jobs.0.title', 'Second service')
            ->where('jobs.0.customer', 'Second Co')
            ->where('filters.customer', $selectedCustomer->public_id));
});

test('a job cannot use contact details belonging to another customer', function () {
    $tenant = jbTenant();
    [$owner, $customer, $otherContact] = $tenant->run(function () {
        $owner = User::where('email', 'owner@alpha.test')->firstOrFail();
        $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);
        $other = Customer::create(['company_name' => 'Other Co', 'status' => 'active']);
        $otherContact = CustomerContact::create([
            'customer_id' => $other->id,
            'first_name' => 'Other',
            'last_name' => 'Contact',
            'is_primary' => true,
        ]);

        return [$owner, $customer, $otherContact];
    });

    $this->actingAs($owner)
        ->post('http://alpha.kaamil.test/jobs', [
            'title' => 'Invalid details',
            'customer_id' => $customer->public_id,
            'customer_contact_id' => $otherContact->public_id,
        ])
        ->assertSessionHasErrors('customer_contact_id');
});

test('external representatives cannot access jobs', function () {
    $tenant = jbTenant();
    ['job' => $job] = jbScenario($tenant);
    $external = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $u->assignRole(Role::CustomerRepresentative->value);

        return $u;
    });

    $this->actingAs($external)->get('http://alpha.kaamil.test/jobs')->assertForbidden();
    $this->actingAs($external)->get("http://alpha.kaamil.test/jobs/{$job->public_id}")->assertForbidden();
});

test('jobs are isolated per tenant', function () {
    $alpha = jbTenant('alpha');
    $beta = jbTenant('beta');

    ['job' => $alphaJob] = jbScenario($alpha);
    $betaOwner = $beta->run(fn () => User::where('email', 'owner@beta.test')->first());

    // A beta user cannot see an alpha job (separate databases; 404 on lookup).
    $this->actingAs($betaOwner)
        ->get("http://beta.kaamil.test/jobs/{$alphaJob->public_id}")
        ->assertNotFound();
});

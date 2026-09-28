<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Documents\DocumentService;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Generators\JobServiceReportGenerator;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Support\DocumentContext;
use App\Domain\Jobs\Actions\CompleteJobAction;
use App\Domain\Jobs\Actions\CreateJobAction;
use App\Domain\Jobs\Actions\StartJobAction;
use App\Domain\Jobs\Models\Job;
use App\Domain\Settings\TenantSettings;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

function dcTenant(string $slug = 'alpha'): Tenant
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
 * A completed job with customer, address, notes and a technician.
 *
 * @return array<string, mixed>
 */
function dcJob(Tenant $tenant): array
{
    return $tenant->run(function (): array {
        $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active', 'email' => 'hi@client.test']);
        $contact = $customer->contacts()->create(['first_name' => 'Dana', 'last_name' => 'Ree', 'phone' => '555-1000']);
        $address = $customer->addresses()->create([
            'type' => 'service', 'address_line_1' => '10 Field Rd', 'city' => 'Metro', 'postal_code' => '90001', 'country_code' => 'US',
        ]);

        $tech = User::factory()->create(['user_type' => 'internal', 'status' => 'active', 'name' => 'Tess Tech']);
        $tech->assignRole(Role::Technician->value);

        $job = app(CreateJobAction::class)->handle([
            'title' => 'Boiler service',
            'customer_id' => $customer->id,
            'customer_contact_id' => $contact->id,
            'service_address_id' => $address->id,
            'assigned_user_id' => $tech->id,
            'service_notes' => 'Replaced valve and flushed the system.',
        ], $tech);
        app(StartJobAction::class)->handle($job, $tech);
        app(CompleteJobAction::class)->handle($job->refresh(), $tech);

        return compact('customer', 'tech', 'job');
    });
}

test('the generator receives tenant settings and stores document metadata', function () {
    $tenant = dcTenant();
    ['tech' => $tech, 'job' => $job] = dcJob($tenant);

    $document = $tenant->run(function () use ($tech, $job) {
        app(TenantSettings::class)->set('general', 'company_name', 'Acme Field Services');

        return app(DocumentService::class)->generate(DocumentType::JobServiceReport, $job, $tech);
    });

    expect($document->document_type)->toBe(DocumentType::JobServiceReport)
        ->and($document->mime_type)->toBe('application/pdf')
        ->and($document->size_bytes)->toBeGreaterThan(0)
        ->and($document->checksum)->not->toBeNull()
        ->and($document->number)->toBe($job->number)
        ->and($document->generated_by)->toBe($tech->id)
        ->and($document->metadata['job'])->toBe($job->public_id)
        ->and($document->related_type)->toBe(Job::class);

    // File stored privately under a tenant-prefixed path.
    $tenant->run(function () use ($document, $tenant) {
        expect($document->path)->toStartWith('documents/'.$tenant->id.'/job_service_report/')
            ->and(Storage::disk($document->disk)->exists($document->path))->toBeTrue();

        $bytes = Storage::disk($document->disk)->get($document->path);
        expect(substr($bytes, 0, 4))->toBe('%PDF');
    });
});

test('the job report HTML includes the required fields and tenant branding', function () {
    $tenant = dcTenant();
    ['tech' => $tech, 'job' => $job] = dcJob($tenant);

    $html = $tenant->run(function () use ($tech, $job) {
        $settings = app(TenantSettings::class);
        $settings->set('general', 'company_name', 'Acme Field Services');

        $context = new DocumentContext(DocumentType::JobServiceReport, $job, $tech, $settings);

        return app(JobServiceReportGenerator::class)->renderHtml($context);
    });

    expect($html)->toContain('Acme Field Services')       // branding
        ->toContain($job->number)                          // form number
        ->toContain('Client Co')                           // customer
        ->toContain('10 Field Rd')                         // service address
        ->toContain('Replaced valve')                      // service notes
        ->toContain('Tess Tech')                           // technician
        ->toContain('Teslim eden')                         // acceptance labels (localized)
        ->toContain('Teslim alan');
});

test('a report with no photos and long notes still renders', function () {
    $tenant = dcTenant();
    ['tech' => $tech, 'job' => $job] = dcJob($tenant);

    $tenant->run(function () use ($tech, $job) {
        $job->update(['service_notes' => str_repeat('Long diagnostic notes across pages. ', 400)]);

        // No images/signature attached — optional assets are absent.
        $document = app(DocumentService::class)->generate(DocumentType::JobServiceReport, $job->refresh(), $tech);

        expect($document->size_bytes)->toBeGreaterThan(1000)
            ->and(substr((string) Storage::disk($document->disk)->get($document->path), 0, 4))->toBe('%PDF');
    });
});

test('a document download requires a valid signature', function () {
    $tenant = dcTenant();
    ['tech' => $tech, 'job' => $job] = dcJob($tenant);
    $document = $tenant->run(fn () => app(DocumentService::class)->generate(DocumentType::JobServiceReport, $job, $tech));

    // Unsigned URL is rejected.
    $this->actingAs($tech)
        ->get("http://alpha.kaamil.test/documents/{$document->public_id}/download")
        ->assertForbidden();

    // Signed URL succeeds for an authorized internal user. The URL must be built
    // on the tenant host (as it always is inside a real tenant request).
    $signed = $tenant->run(function () use ($document) {
        URL::forceRootUrl('http://alpha.kaamil.test');

        return $document->temporaryDownloadUrl();
    });

    $this->actingAs($tech)->get($signed)->assertOk();
});

test('external representatives cannot download an internal job report', function () {
    $tenant = dcTenant();
    ['tech' => $tech, 'job' => $job] = dcJob($tenant);
    $document = $tenant->run(fn () => app(DocumentService::class)->generate(DocumentType::JobServiceReport, $job, $tech));

    $external = $tenant->run(function () {
        $u = User::factory()->create(['user_type' => 'external', 'status' => 'active']);
        $u->assignRole(Role::CustomerRepresentative->value);

        return $u;
    });

    $signed = $tenant->run(function () use ($document) {
        URL::forceRootUrl('http://alpha.kaamil.test');

        return $document->temporaryDownloadUrl();
    });

    // Even with a valid signature, the policy denies an external user.
    $this->actingAs($external)->get($signed)->assertForbidden();
});

test('generated document file paths are isolated per tenant', function () {
    $alpha = dcTenant('alpha');
    $beta = dcTenant('beta');

    ['tech' => $aTech, 'job' => $aJob] = dcJob($alpha);
    $alphaDoc = $alpha->run(fn () => app(DocumentService::class)->generate(DocumentType::JobServiceReport, $aJob, $aTech));

    ['tech' => $bTech, 'job' => $bJob] = dcJob($beta);
    $betaDoc = $beta->run(fn () => app(DocumentService::class)->generate(DocumentType::JobServiceReport, $bJob, $bTech));

    expect($alphaDoc->path)->toContain('documents/'.$alpha->id.'/')
        ->and($betaDoc->path)->toContain('documents/'.$beta->id.'/')
        ->and($alphaDoc->path)->not->toBe($betaDoc->path);

    // Each document exists only in its own tenant database (separate schemas).
    expect($alpha->run(fn () => GeneratedDocument::where('public_id', $betaDoc->public_id)->exists()))->toBeFalse()
        ->and($beta->run(fn () => GeneratedDocument::where('public_id', $alphaDoc->public_id)->exists()))->toBeFalse();
});

test('the job report endpoint generates and lists a downloadable document', function () {
    $tenant = dcTenant();
    ['tech' => $tech, 'job' => $job] = dcJob($tenant);

    $this->actingAs($tech)
        ->post("http://alpha.kaamil.test/jobs/{$job->public_id}/report")
        ->assertRedirect();

    expect($tenant->run(fn () => GeneratedDocument::where('related_id', $job->id)->count()))->toBe(1);
});

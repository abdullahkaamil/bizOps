<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Quotations\Actions\CreateQuotationAction;
use App\Domain\Quotations\Actions\SendQuotationAction;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Models\Quotation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\URL;

function pqTenant(): Tenant
{
    return (new CreateTenant)->handle([
        'name' => 'Alpha',
        'subdomain' => 'alpha',
        'admin_name' => 'Alpha Owner',
        'admin_email' => 'owner@alpha.test',
        'admin_password' => 'password123',
        'license_expires_at' => now()->addMonth(),
    ]);
}

/**
 * A tenant with an owner and a *sent* quotation ready for the customer to decide.
 *
 * @return array{tenant: Tenant, owner: User, quotation: Quotation}
 */
function pqSentQuotation(): array
{
    // Signed URLs incorporate the host — generate them for the tenant subdomain
    // so the test client's requests carry a matching signature.
    URL::forceRootUrl('http://alpha.kaamil.test');

    $tenant = pqTenant();

    $data = $tenant->run(function (): array {
        $owner = User::where('email', 'owner@alpha.test')->first();
        $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active']);

        $quotation = app(CreateQuotationAction::class)->handle([
            'customer_id' => $customer->id,
            'lines' => [['customer_alias' => 'Widget', 'quantity' => 2, 'unit_price' => 50, 'tax_rate' => 10]],
        ], $owner);

        app(SendQuotationAction::class)->handle($quotation, $owner); // draft -> sent

        return ['owner' => $owner, 'quotation' => $quotation->fresh()];
    });

    return ['tenant' => $tenant, 'owner' => $data['owner'], 'quotation' => $data['quotation']];
}

function pqUrl(string $name, string $publicId): string
{
    return URL::signedRoute($name, ['quotation' => $publicId]);
}

test('the public review link requires a valid signature', function () {
    ['quotation' => $q] = pqSentQuotation();
    $base = 'http://alpha.kaamil.test';

    // Unsigned request is rejected.
    $this->get("{$base}/quotations/{$q->public_id}/review")->assertForbidden();

    // A validly signed request renders the public page.
    $this->get(pqUrl('tenant.quotations.review', $q->public_id))
        ->assertOk()
        ->assertInertia(fn ($p) => $p->component('quotations/PublicReview'));
});

test('the public review never exposes internal cost', function () {
    ['quotation' => $q] = pqSentQuotation();

    $this->get(pqUrl('tenant.quotations.review', $q->public_id))
        ->assertInertia(fn ($p) => $p
            ->has('quotation.lines.0.unit_price')
            ->missing('quotation.lines.0.unit_cost')
            ->missing('quotation.lines.0.internal_name'));
});

test('a customer can accept a quotation through the signed link', function () {
    ['tenant' => $tenant, 'owner' => $owner, 'quotation' => $q] = pqSentQuotation();

    $this->post(pqUrl('tenant.quotations.review.accept', $q->public_id), ['name' => 'Ayşe Yılmaz'])
        ->assertRedirect();

    $tenant->run(function () use ($q, $owner) {
        $fresh = $q->fresh();
        expect($fresh->status)->toBe(QuotationStatus::Accepted)
            ->and($fresh->decided_by_name)->toBe('Ayşe Yılmaz')
            ->and($fresh->decided_ip)->not->toBeNull()
            ->and($fresh->accepted_at)->not->toBeNull();

        // An actor-less audit row records the customer decision.
        $last = $fresh->statusHistory()->orderByDesc('id')->first();
        expect($last->to_status)->toBe(QuotationStatus::Accepted)
            ->and($last->actor_id)->toBeNull();

        // The internal owner is notified.
        expect($owner->notifications()->get()
            ->contains(fn ($n) => $n->data['type'] === NotificationType::QuotationDecided->value))->toBeTrue();
    });
});

test('a customer can reject a quotation with a reason', function () {
    ['tenant' => $tenant, 'quotation' => $q] = pqSentQuotation();

    $this->post(pqUrl('tenant.quotations.review.reject', $q->public_id), [
        'name' => 'Mehmet Demir', 'reason' => 'Bütçemizi aştı.',
    ])->assertRedirect();

    $tenant->run(function () use ($q) {
        $fresh = $q->fresh();
        expect($fresh->status)->toBe(QuotationStatus::Rejected)
            ->and($fresh->rejected_at)->not->toBeNull();

        $last = $fresh->statusHistory()->orderByDesc('id')->first();
        expect($last->to_status)->toBe(QuotationStatus::Rejected)
            ->and($last->reason)->toBe('Bütçemizi aştı.');
    });
});

test('deciding an already-decided quotation is a harmless no-op', function () {
    ['tenant' => $tenant, 'quotation' => $q] = pqSentQuotation();

    $this->post(pqUrl('tenant.quotations.review.accept', $q->public_id), ['name' => 'First'])->assertRedirect();

    // A second submission (e.g. a re-opened link) does not error or change the decision.
    $this->post(pqUrl('tenant.quotations.review.reject', $q->public_id), ['name' => 'Second'])
        ->assertRedirect();

    expect($tenant->run(fn () => $q->fresh()->status))->toBe(QuotationStatus::Accepted);
    expect($tenant->run(fn () => $q->fresh()->decided_by_name))->toBe('First');
});

test('the internal show page surfaces the review link only while sent', function () {
    ['owner' => $owner, 'quotation' => $q] = pqSentQuotation();
    $base = 'http://alpha.kaamil.test';

    $this->actingAs($owner)->get("{$base}/quotations/{$q->public_id}")
        ->assertInertia(fn ($p) => $p->where('reviewLink', fn ($link) => is_string($link) && str_contains($link, '/review')));

    // Once decided, the link is gone.
    $this->post(pqUrl('tenant.quotations.review.accept', $q->public_id), ['name' => 'Done'])->assertRedirect();

    $this->actingAs($owner)->get("{$base}/quotations/{$q->public_id}")
        ->assertInertia(fn ($p) => $p->where('reviewLink', null));
});

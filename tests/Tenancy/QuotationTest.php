<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Generators\QuotationGenerator;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Support\DocumentContext;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Quotations\Actions\AcceptQuotationAction;
use App\Domain\Quotations\Actions\CancelQuotationAction;
use App\Domain\Quotations\Actions\CreateQuotationAction;
use App\Domain\Quotations\Actions\DuplicateQuotationAction;
use App\Domain\Quotations\Actions\ExpireQuotationAction;
use App\Domain\Quotations\Actions\SendQuotationAction;
use App\Domain\Quotations\Actions\UpdateQuotationAction;
use App\Domain\Quotations\Enums\QuotationStatus;
use App\Domain\Quotations\Exceptions\InvalidQuotationTransition;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Settings\TenantSettings;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

function qtTenant(string $slug = 'alpha'): Tenant
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

function qtSales(): User
{
    $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
    $u->assignRole(Role::Sales->value);

    return $u;
}

/**
 * @param  array<int, array<string, mixed>>  $lines
 */
function qtCreate(User $actor, array $lines, ?string $itemPublicId = null): Quotation
{
    $customer = Customer::create(['company_name' => 'Client Co', 'status' => 'active', 'email' => 'c@x.test']);

    return app(CreateQuotationAction::class)->handle([
        'customer_id' => $customer->id,
        'lines' => $lines,
    ], $actor);
}

test('server totals are computed in minor units and are correct', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        // 2 x 10.00, 10% tax -> subtotal 2000, tax 200, total 2200 (minor units)
        // plus 1 x 5.00 with a 1.00 fixed discount, 0% tax -> subtotal 500, discount 100, total 400
        $q = qtCreate($actor, [
            ['customer_alias' => 'Widget', 'quantity' => 2, 'unit_price' => 10, 'tax_rate' => 10],
            ['customer_alias' => 'Gadget', 'quantity' => 1, 'unit_price' => 5, 'tax_rate' => 0, 'discount_type' => 'fixed', 'discount_value' => 1],
        ]);

        expect($q->subtotal)->toBe(2500)
            ->and($q->discount_total)->toBe(100)
            ->and($q->tax_total)->toBe(200)
            ->and($q->grand_total)->toBe(2600);
    });
});

test('a historical quotation does not change when the item price later changes', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $item = InventoryItem::create(['sku' => 'X', 'name' => 'Ubiquiti U6 Pro', 'unit' => 'unit', 'status' => 'active', 'current_sale_price' => 100]);

        $q = app(CreateQuotationAction::class)->handle([
            'customer_id' => Customer::create(['company_name' => 'C', 'status' => 'active'])->id,
            'lines' => [[
                'inventory_item_id' => $item->public_id,
                'customer_alias' => 'Corporate Wi-Fi Access Point',
                'quantity' => 1, 'tax_rate' => 0,
            ]],
        ], $actor);

        $originalTotal = $q->grand_total;
        expect($originalTotal)->toBe(10000); // 100.00 snapshotted

        // Change the catalogue price afterwards.
        $item->update(['current_sale_price' => 250]);

        expect($q->fresh()->grand_total)->toBe($originalTotal)
            ->and($q->lines()->first()->unit_price)->toBe(10000)
            ->and($q->lines()->first()->internal_name_snapshot)->toBe('Ubiquiti U6 Pro')
            ->and($q->lines()->first()->customer_alias)->toBe('Corporate Wi-Fi Access Point');
    });
});

test('the customer PDF shows the alias, not the internal name', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $item = InventoryItem::create(['sku' => 'NET-001', 'name' => 'Ubiquiti U6 Pro', 'unit' => 'unit', 'status' => 'active', 'current_sale_price' => 199]);

        $q = app(CreateQuotationAction::class)->handle([
            'customer_id' => Customer::create(['company_name' => 'C', 'status' => 'active'])->id,
            'lines' => [[
                'inventory_item_id' => $item->public_id,
                'customer_alias' => 'Corporate Wi-Fi Access Point',
                'quantity' => 1, 'tax_rate' => 0,
            ]],
        ], $actor);

        $html = app(QuotationGenerator::class)->renderHtml(
            new DocumentContext(DocumentType::Quotation, $q, $actor, app(TenantSettings::class)),
        );

        expect($html)->toContain('Corporate Wi-Fi Access Point')
            ->and($html)->not->toContain('Ubiquiti U6 Pro');
    });
});

test('internal cost is hidden on the quotation without permission', function () {
    $tenant = qtTenant();
    $q = $tenant->run(function () {
        $actor = qtSales();
        $item = InventoryItem::create(['sku' => 'X', 'name' => 'Part', 'unit' => 'unit', 'status' => 'active', 'current_sale_price' => 50]);

        return app(CreateQuotationAction::class)->handle([
            'customer_id' => Customer::create(['company_name' => 'C', 'status' => 'active'])->id,
            'lines' => [['inventory_item_id' => $item->public_id, 'customer_alias' => 'Alias', 'quantity' => 1]],
        ], $actor);
    });

    // Sales has quotations.view but NOT inventory.view_cost.
    $sales = $tenant->run(fn () => qtSales());
    $this->actingAs($sales)
        ->get("http://alpha.kaamil.test/quotations/{$q->public_id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('canViewCost', false)
            ->where('quotation.lines.0.unit_cost', null)
            ->where('quotation.lines.0.internal_name', null));

    // Owner has view_cost.
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $this->actingAs($owner)
        ->get("http://alpha.kaamil.test/quotations/{$q->public_id}")
        ->assertInertia(fn ($p) => $p->where('canViewCost', true)->where('quotation.lines.0.internal_name', 'Part'));
});

test('a sent quotation is locked against edits', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]);

        app(SendQuotationAction::class)->handle($q, $actor);
        expect($q->fresh()->status)->toBe(QuotationStatus::Sent);

        expect(fn () => app(UpdateQuotationAction::class)->handle($q->fresh(), [
            'lines' => [['customer_alias' => 'B', 'quantity' => 9, 'unit_price' => 99]],
        ], $actor))->toThrow(InvalidQuotationTransition::class);
    });
});

test('sending generates a quotation PDF and it can be accepted', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]);

        app(SendQuotationAction::class)->handle($q, $actor);
        expect(GeneratedDocument::where('document_type', DocumentType::Quotation->value)->count())->toBe(1);

        app(AcceptQuotationAction::class)->handle($q->fresh(), $actor);
        expect($q->fresh()->status)->toBe(QuotationStatus::Accepted)
            ->and($q->fresh()->accepted_at)->not->toBeNull();
    });
});

test('duplicating a quotation creates a new draft', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 2, 'unit_price' => 10, 'tax_rate' => 10]]);
        app(SendQuotationAction::class)->handle($q, $actor);

        $copy = app(DuplicateQuotationAction::class)->handle($q->fresh(), $actor);

        expect($copy->status)->toBe(QuotationStatus::Draft)
            ->and($copy->number)->not->toBe($q->number)
            ->and($copy->grand_total)->toBe($q->fresh()->grand_total)
            ->and($copy->lines()->count())->toBe(1)
            ->and($copy->sent_at)->toBeNull();
    });
});

test('an expired quotation cannot be accepted', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]);
        app(SendQuotationAction::class)->handle($q, $actor);

        app(ExpireQuotationAction::class)->handle($q->fresh(), $actor);
        expect($q->fresh()->status)->toBe(QuotationStatus::Expired);

        expect(fn () => app(AcceptQuotationAction::class)->handle($q->fresh(), $actor))
            ->toThrow(InvalidQuotationTransition::class);
    });
});

test('quotations are isolated per tenant', function () {
    $alpha = qtTenant('alpha');
    $beta = qtTenant('beta');

    $alphaQuote = $alpha->run(fn () => qtCreate(qtSales(), [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]));

    expect($beta->run(fn () => Quotation::where('public_id', $alphaQuote->public_id)->exists()))->toBeFalse()
        ->and($alpha->run(fn () => Quotation::count()))->toBe(1);
});

test('a draft quotation can be cancelled', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]);

        app(CancelQuotationAction::class)->handle($q, $actor, 'Client withdrew');

        expect($q->fresh()->status)->toBe(QuotationStatus::Canceled);
    });
});

test('a sent quotation can be cancelled', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]);
        app(SendQuotationAction::class)->handle($q, $actor);

        app(CancelQuotationAction::class)->handle($q->fresh(), $actor);

        expect($q->fresh()->status)->toBe(QuotationStatus::Canceled);
    });
});

test('a terminal quotation cannot be cancelled', function () {
    $tenant = qtTenant();

    $tenant->run(function () {
        $actor = qtSales();
        $q = qtCreate($actor, [['customer_alias' => 'A', 'quantity' => 1, 'unit_price' => 10]]);
        app(SendQuotationAction::class)->handle($q, $actor);
        app(AcceptQuotationAction::class)->handle($q->fresh(), $actor);

        expect(fn () => app(CancelQuotationAction::class)->handle($q->fresh(), $actor))
            ->toThrow(InvalidQuotationTransition::class);
    });
});

test('a quotation can be created and updated with a chosen currency', function () {
    $tenant = qtTenant();
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $customer = $tenant->run(fn () => Customer::create(['company_name' => 'Client Co', 'status' => 'active']));
    $base = 'http://alpha.kaamil.test';
    $lines = [['customer_alias' => 'Item', 'quantity' => 1, 'unit_price' => 10]];

    $this->actingAs($owner)
        ->post("{$base}/quotations", ['customer_id' => $customer->public_id, 'currency' => 'EUR', 'lines' => $lines])
        ->assertRedirect();

    $q = $tenant->run(fn () => Quotation::latest('id')->first());
    expect($q->currency)->toBe('EUR');

    // Editing to a different supported currency persists it.
    $this->actingAs($owner)
        ->put("{$base}/quotations/{$q->public_id}", ['customer_id' => $customer->public_id, 'currency' => 'GBP', 'lines' => $lines])
        ->assertRedirect();

    expect($tenant->run(fn (): string => $q->fresh()->currency))->toBe('GBP');

    // An unsupported currency is rejected.
    $this->actingAs($owner)
        ->post("{$base}/quotations", ['customer_id' => $customer->public_id, 'currency' => 'JPY', 'lines' => $lines])
        ->assertSessionHasErrors('currency');
});

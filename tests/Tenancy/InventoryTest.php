<?php

use App\Actions\Tenancy\CreateTenant;
use App\Domain\CRM\Models\Customer;
use App\Domain\Inventory\Actions\AddPartToWorkshopTicketAction;
use App\Domain\Inventory\Actions\RemovePartFromWorkshopTicketAction;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Exceptions\InsufficientStock;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Inventory\Models\WorkshopTicketPart;
use App\Domain\Inventory\StockLedger;
use App\Domain\Settings\TenantSettings;
use App\Domain\Workshop\Actions\CreateWorkshopTicketAction;
use App\Enums\Role;
use App\Models\Tenant;
use App\Models\User;

function invTenant(string $slug = 'alpha'): Tenant
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

function invItem(string $sku = 'SKU-1', ?float $sale = 20.0): InventoryItem
{
    return InventoryItem::create(['sku' => $sku, 'name' => 'Widget', 'unit' => 'unit', 'status' => 'active', 'current_sale_price' => $sale]);
}

function invTech(): User
{
    $u = User::factory()->create(['user_type' => 'internal', 'status' => 'active']);
    $u->assignRole(Role::Technician->value);

    return $u;
}

test('a stock movement changes the cached balance and reconciles with the ledger', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $item = invItem();
        $wh = Warehouse::default();
        $ledger = app(StockLedger::class);

        $ledger->record($item, $wh, MovementType::Opening, 10, invTech());
        $ledger->record($item, $wh, MovementType::AdjustmentOut, 3, invTech());

        expect($ledger->balance($item->id, $wh->id))->toBe(7.0)
            ->and($ledger->reconciledBalance($item->id, $wh->id))->toBe(7.0);
    });
});

test('workshop consumption is transactional and snapshots cost/price', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $tech = invTech();
        $item = invItem('SKU-1', 25.0);
        $wh = Warehouse::default();
        app(StockLedger::class)->record($item, $wh, MovementType::Opening, 10, $tech);
        // Provide a purchase cost to snapshot.
        app(StockLedger::class)->record($item, $wh, MovementType::Purchase, 5, $tech, ['unit_cost' => 8.5]);

        $customer = Customer::create(['company_name' => 'C', 'status' => 'active']);
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);

        $part = app(AddPartToWorkshopTicketAction::class)->handle($ticket, $item, 2, $tech);

        expect($part->quantity)->toBe('2.000')
            ->and((float) $part->sale_price_snapshot)->toBe(25.0)
            ->and((float) $part->unit_cost_snapshot)->toBe(8.5)
            ->and(app(StockLedger::class)->balance($item->id, $wh->id))->toBe(13.0)
            ->and(StockMovement::where('type', MovementType::WorkshopConsumption->value)->count())->toBe(1);
    });
});

test('consumption respects the negative-stock tenant setting', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $tech = invTech();
        $item = invItem();
        $wh = Warehouse::default();
        app(StockLedger::class)->record($item, $wh, MovementType::Opening, 1, $tech);

        // Negative stock disabled (default) -> cannot consume more than available.
        expect(fn () => app(StockLedger::class)->record($item, $wh, MovementType::WorkshopConsumption, 5, $tech))
            ->toThrow(InsufficientStock::class);

        // Enable negative stock -> now allowed.
        app(TenantSettings::class)->set('inventory', 'allow_negative_stock', true);
        app(StockLedger::class)->record($item, $wh, MovementType::WorkshopConsumption, 5, $tech);

        expect(app(StockLedger::class)->balance($item->id, $wh->id))->toBe(-4.0);
    });
});

test('removing a part creates a reversal movement and restores stock', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $tech = invTech();
        $item = invItem();
        $wh = Warehouse::default();
        app(StockLedger::class)->record($item, $wh, MovementType::Opening, 10, $tech);

        $customer = Customer::create(['company_name' => 'C', 'status' => 'active']);
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);

        $part = app(AddPartToWorkshopTicketAction::class)->handle($ticket, $item, 3, $tech);
        expect(app(StockLedger::class)->balance($item->id, $wh->id))->toBe(7.0);

        app(RemovePartFromWorkshopTicketAction::class)->handle($part, $tech);

        expect(app(StockLedger::class)->balance($item->id, $wh->id))->toBe(10.0)
            ->and(StockMovement::where('type', MovementType::WorkshopReturn->value)->count())->toBe(1)
            ->and(WorkshopTicketPart::count())->toBe(0);
    });
});

test('a historical cost snapshot stays stable when catalogue prices change', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $tech = invTech();
        $item = invItem('SKU-1', 20.0);
        $wh = Warehouse::default();
        app(StockLedger::class)->record($item, $wh, MovementType::Opening, 10, $tech);

        $customer = Customer::create(['company_name' => 'C', 'status' => 'active']);
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S1', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);
        $part = app(AddPartToWorkshopTicketAction::class)->handle($ticket, $item, 1, $tech);

        // Change the catalogue sale price afterwards.
        $item->update(['current_sale_price' => 99.0]);

        expect((float) $part->fresh()->sale_price_snapshot)->toBe(20.0);
    });
});

test('unauthorized users cannot see cost fields on an item', function () {
    $tenant = invTenant();
    $item = $tenant->run(function () {
        $i = invItem();
        app(StockLedger::class)->record($i, Warehouse::default(), MovementType::Purchase, 5, invTech(), ['unit_cost' => 7.5]);

        return $i;
    });

    // Technician: has inventory.view but NOT inventory.view_cost.
    $tech = $tenant->run(fn () => invTech());
    $this->actingAs($tech)
        ->get("http://alpha.kaamil.test/inventory/{$item->public_id}")
        ->assertOk()
        ->assertInertia(fn ($p) => $p
            ->where('canViewCost', false)
            ->where('movements.0.unit_cost', null));

    // Owner: has all permissions incl. view_cost.
    $owner = $tenant->run(fn () => User::where('email', 'owner@alpha.test')->first());
    $this->actingAs($owner)
        ->get("http://alpha.kaamil.test/inventory/{$item->public_id}")
        ->assertInertia(fn ($p) => $p->where('canViewCost', true)->where('movements.0.unit_cost', '7.50'));
});

test('over-consumption is prevented under sequential locking when negative stock is off', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $tech = invTech();
        $item = invItem();
        $wh = Warehouse::default();
        app(StockLedger::class)->record($item, $wh, MovementType::Opening, 5, $tech);

        app(StockLedger::class)->record($item, $wh, MovementType::WorkshopConsumption, 3, $tech);

        // The second consumer cannot oversell the remaining 2 units.
        expect(fn () => app(StockLedger::class)->record($item, $wh, MovementType::WorkshopConsumption, 3, $tech))
            ->toThrow(InsufficientStock::class);

        expect(app(StockLedger::class)->balance($item->id, $wh->id))->toBe(2.0);
    });
});

test('inventory is isolated per tenant', function () {
    $alpha = invTenant('alpha');
    $beta = invTenant('beta');

    $alphaItem = $alpha->run(fn () => invItem('SHARED-SKU'));
    // The same SKU is free to use in another tenant (separate schemas).
    $betaItem = $beta->run(fn () => invItem('SHARED-SKU'));

    expect($beta->run(fn () => InventoryItem::where('public_id', $alphaItem->public_id)->exists()))->toBeFalse()
        ->and($alpha->run(fn () => InventoryItem::count()))->toBe(1)
        ->and($betaItem->sku)->toBe('SHARED-SKU');
});

test('part cost is snapshotted as an exact decimal string, never a float', function () {
    $tenant = invTenant();

    $tenant->run(function () {
        $tech = invTech();
        $item = invItem('SKU-EX', 19.99);
        $wh = Warehouse::default();
        app(StockLedger::class)->record($item, $wh, MovementType::Opening, 10, $tech);
        // Pass the cost as an exact string (money never travels as a float).
        app(StockLedger::class)->record($item, $wh, MovementType::Purchase, 5, $tech, ['unit_cost' => '19.99']);

        $customer = Customer::create(['company_name' => 'C', 'status' => 'active']);
        $ticket = app(CreateWorkshopTicketAction::class)->handle([
            'customer_id' => $customer->id, 'serial_number' => 'S9', 'brand' => 'A', 'model' => 'B', 'issue_description' => 'x',
        ], $tech);

        $part = app(AddPartToWorkshopTicketAction::class)->handle($ticket, $item, 1, $tech);

        // decimal(12,2) column -> exact string, no float rounding artefacts.
        expect($part->unit_cost_snapshot)->toBe('19.99')
            ->and($part->unit_cost_snapshot)->toBeString();
    });
});

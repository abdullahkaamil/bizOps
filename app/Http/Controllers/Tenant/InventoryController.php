<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Inventory\Actions\AdjustStockAction;
use App\Domain\Inventory\Enums\InventoryItemStatus;
use App\Domain\Inventory\Enums\MovementType;
use App\Domain\Inventory\Enums\PriceType;
use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\InventoryPriceHistory;
use App\Domain\Inventory\Models\StockMovement;
use App\Domain\Inventory\Models\Supplier;
use App\Domain\Inventory\Models\Warehouse;
use App\Domain\Settings\TenantSettings;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\AdjustStockRequest;
use App\Http\Requests\Tenant\AttachSupplierRequest;
use App\Http\Requests\Tenant\StoreInventoryItemRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class InventoryController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', InventoryItem::class);

        $threshold = app(TenantSettings::class)->inventoryLowStockThreshold();
        $lowOnly = $request->boolean('low');

        $items = InventoryItem::query()
            ->withSum('balances as stock', 'quantity')
            ->orderBy('name')
            ->get()
            ->map(fn (InventoryItem $i): array => [
                'id' => $i->public_id,
                'sku' => $i->sku,
                'name' => $i->name,
                'unit' => $i->unit,
                'status' => $i->status->value,
                'sale_price' => $i->current_sale_price,
                'stock' => (float) ($i->stock ?? 0),
                'low' => (float) ($i->stock ?? 0) <= $threshold,
            ]);

        if ($lowOnly) {
            $items = $items->filter(fn (array $i): bool => $i['low'])->values();
        }

        return Inertia::render('inventory/Index', [
            'items' => $items->all(),
            'lowOnly' => $lowOnly,
            'threshold' => $threshold,
            'canManage' => $request->user()->can('create', InventoryItem::class),
        ]);
    }

    public function create(): Response
    {
        $this->authorize('create', InventoryItem::class);

        return Inertia::render('inventory/Create', ['statuses' => InventoryItemStatus::values()]);
    }

    public function store(StoreInventoryItemRequest $request): RedirectResponse
    {
        $this->authorize('create', InventoryItem::class);

        $item = InventoryItem::create($request->validated());
        $this->recordSalePrice($item, $request->user()?->id);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item created.')]);

        return to_route('tenant.inventory.show', $item);
    }

    public function show(Request $request, InventoryItem $item): Response
    {
        $this->authorize('view', $item);

        $canViewCost = $request->user()->can('viewCost', InventoryItem::class);

        $item->load(['balances.warehouse']);

        return Inertia::render('inventory/Show', [
            'item' => [
                'id' => $item->public_id,
                'sku' => $item->sku,
                'name' => $item->name,
                'description' => $item->description,
                'unit' => $item->unit,
                'status' => $item->status->value,
                'sale_price' => $item->current_sale_price,
            ],
            'balances' => $item->balances->map(fn ($b): array => [
                'warehouse' => $b->warehouse?->name,
                'quantity' => (float) $b->quantity,
            ])->all(),
            'movements' => $item->movements()->with('warehouse', 'actor')->latest('id')->limit(50)->get()
                ->map(fn (StockMovement $m): array => [
                    'id' => $m->public_id,
                    'type' => $m->type->value,
                    'quantity' => (float) $m->quantity,
                    'warehouse' => $m->warehouse?->name,
                    'unit_cost' => $canViewCost ? $m->unit_cost : null,
                    'reason' => $m->reason,
                    'actor' => $m->actor?->name,
                    'occurred_at' => $m->occurred_at?->toIso8601String(),
                ])->all(),
            'suppliers' => DB::table('inventory_item_suppliers')
                ->join('suppliers', 'suppliers.id', '=', 'inventory_item_suppliers.supplier_id')
                ->where('inventory_item_suppliers.inventory_item_id', $item->id)
                ->orderBy('suppliers.name')
                ->get([
                    'suppliers.public_id', 'suppliers.name',
                    'inventory_item_suppliers.supplier_sku', 'inventory_item_suppliers.is_preferred',
                    'inventory_item_suppliers.lead_time_days', 'inventory_item_suppliers.last_purchase_price',
                ])
                ->map(fn (object $r): array => [
                    'id' => $r->public_id,
                    'name' => $r->name,
                    'supplier_sku' => $r->supplier_sku,
                    'is_preferred' => (bool) $r->is_preferred,
                    'lead_time_days' => $r->lead_time_days,
                    'last_purchase_price' => $canViewCost ? $r->last_purchase_price : null,
                ])->all(),
            'priceHistory' => $canViewCost
                ? $item->priceHistory()->limit(50)->get()->map(fn (InventoryPriceHistory $p): array => [
                    'type' => $p->price_type->value,
                    'amount' => $p->amount,
                    'currency' => $p->currency,
                    'effective_at' => $p->effective_at?->toIso8601String(),
                ])->all()
                : [],
            'canViewCost' => $canViewCost,
            'canManage' => $request->user()->can('update', $item),
            'movementTypes' => MovementType::adjustableValues(),
            'allSuppliers' => Supplier::query()->orderBy('name')->get(['public_id', 'name'])
                ->map(fn (Supplier $s): array => ['id' => $s->public_id, 'name' => $s->name])->all(),
        ]);
    }

    public function update(StoreInventoryItemRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $priceChanged = (string) $item->current_sale_price !== (string) $request->validated('current_sale_price');
        $item->update($request->validated());

        if ($priceChanged) {
            $this->recordSalePrice($item, $request->user()?->id);
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Item updated.')]);

        return back();
    }

    public function adjust(AdjustStockRequest $request, InventoryItem $item, AdjustStockAction $action): RedirectResponse
    {
        $this->authorize('adjust', $item);

        $warehouse = $request->filled('warehouse_id')
            ? Warehouse::where('public_id', $request->validated('warehouse_id'))->firstOrFail()
            : Warehouse::default();

        $action->handle(
            $item,
            $warehouse,
            MovementType::from($request->validated('type')),
            (float) $request->validated('quantity'),
            $request->user(),
            // Money stays an exact decimal string end-to-end (never a float).
            $request->validated('unit_cost'),
            $request->validated('reason'),
        );

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Stock adjusted.')]);

        return back();
    }

    public function attachSupplier(AttachSupplierRequest $request, InventoryItem $item): RedirectResponse
    {
        $this->authorize('update', $item);

        $supplier = Supplier::where('public_id', $request->validated('supplier_id'))->firstOrFail();

        $item->suppliers()->syncWithoutDetaching([$supplier->id => [
            'public_id' => (string) Str::uuid(),
            'supplier_sku' => $request->validated('supplier_sku'),
            'last_purchase_price' => $request->validated('last_purchase_price'),
            'lead_time_days' => $request->validated('lead_time_days'),
            'is_preferred' => $request->validated('is_preferred'),
        ]]);

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier linked.')]);

        return back();
    }

    protected function recordSalePrice(InventoryItem $item, ?int $userId): void
    {
        if ($item->current_sale_price === null) {
            return;
        }

        InventoryPriceHistory::create([
            'inventory_item_id' => $item->id,
            'price_type' => PriceType::Sale,
            'amount' => $item->current_sale_price,
            'currency' => app(TenantSettings::class)->currency(),
            'effective_at' => now(),
            'created_by' => $userId,
            'created_at' => now(),
        ]);
    }
}

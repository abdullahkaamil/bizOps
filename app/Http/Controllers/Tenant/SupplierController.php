<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\Inventory\Models\InventoryItem;
use App\Domain\Inventory\Models\Supplier;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreSupplierRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SupplierController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Supplier::class);

        return Inertia::render('suppliers/Index', [
            'suppliers' => Supplier::query()->withCount('items')->orderBy('name')->get()
                ->map(fn (Supplier $s): array => [
                    'id' => $s->public_id,
                    'name' => $s->name,
                    'contact_name' => $s->contact_name,
                    'email' => $s->email,
                    'phone' => $s->phone,
                    'items_count' => $s->items_count,
                ])->all(),
            'canManage' => $request->user()->can('create', Supplier::class),
        ]);
    }

    public function store(StoreSupplierRequest $request): RedirectResponse
    {
        $this->authorize('create', Supplier::class);

        Supplier::create($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier created.')]);

        return back();
    }

    public function show(Request $request, Supplier $supplier): Response
    {
        $this->authorize('view', $supplier);

        $canViewCost = $request->user()->can('viewCost', InventoryItem::class);

        return Inertia::render('suppliers/Show', [
            'supplier' => [
                'id' => $supplier->public_id,
                'name' => $supplier->name,
                'contact_name' => $supplier->contact_name,
                'email' => $supplier->email,
                'phone' => $supplier->phone,
                'address' => $supplier->address,
                'notes' => $supplier->notes,
            ],
            'items' => DB::table('inventory_item_suppliers')
                ->join('inventory_items', 'inventory_items.id', '=', 'inventory_item_suppliers.inventory_item_id')
                ->where('inventory_item_suppliers.supplier_id', $supplier->id)
                ->orderBy('inventory_items.name')
                ->get([
                    'inventory_items.public_id', 'inventory_items.sku', 'inventory_items.name',
                    'inventory_item_suppliers.supplier_sku', 'inventory_item_suppliers.lead_time_days',
                    'inventory_item_suppliers.is_preferred', 'inventory_item_suppliers.last_purchase_price',
                ])
                ->map(fn (object $r): array => [
                    'id' => $r->public_id,
                    'sku' => $r->sku,
                    'name' => $r->name,
                    'supplier_sku' => $r->supplier_sku,
                    'lead_time_days' => $r->lead_time_days,
                    'is_preferred' => (bool) $r->is_preferred,
                    'last_purchase_price' => $canViewCost ? $r->last_purchase_price : null,
                ])->all(),
            'canViewCost' => $canViewCost,
        ]);
    }

    public function update(StoreSupplierRequest $request, Supplier $supplier): RedirectResponse
    {
        $this->authorize('update', $supplier);

        $supplier->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier updated.')]);

        return back();
    }

    public function destroy(Supplier $supplier): RedirectResponse
    {
        $this->authorize('delete', $supplier);

        $supplier->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Supplier removed.')]);

        return to_route('tenant.suppliers.index');
    }
}

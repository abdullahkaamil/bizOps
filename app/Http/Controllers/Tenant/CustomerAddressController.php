<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerAddress;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreAddressRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CustomerAddressController extends Controller
{
    public function store(StoreAddressRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $address = $customer->addresses()->create($request->safe()->except('is_primary'));

        if ($request->boolean('is_primary') || $address->isFirstForCustomer()) {
            $address->makePrimary();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address added.')]);

        return to_route('tenant.customers.show', $customer);
    }

    public function update(StoreAddressRequest $request, Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);

        $address->update($request->safe()->except('is_primary'));

        if ($request->boolean('is_primary')) {
            $address->makePrimary();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address updated.')]);

        return to_route('tenant.customers.show', $customer);
    }

    public function setPrimary(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);

        $address->makePrimary();

        return to_route('tenant.customers.show', $customer);
    }

    public function destroy(Customer $customer, CustomerAddress $address): RedirectResponse
    {
        $this->authorize('update', $customer);

        $wasPrimary = $address->is_primary;
        $address->delete();

        if ($wasPrimary && ($next = $customer->addresses()->first()) !== null) {
            $next->makePrimary();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Address removed.')]);

        return to_route('tenant.customers.show', $customer);
    }
}

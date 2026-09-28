<?php

declare(strict_types=1);

namespace App\Http\Controllers\Tenant;

use App\Domain\CRM\Models\Customer;
use App\Domain\CRM\Models\CustomerContact;
use App\Http\Controllers\Controller;
use App\Http\Requests\Tenant\StoreContactRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class CustomerContactController extends Controller
{
    public function store(StoreContactRequest $request, Customer $customer): RedirectResponse
    {
        $this->authorize('update', $customer);

        $contact = $customer->contacts()->create($request->safe()->except('is_primary'));

        if ($request->boolean('is_primary') || $contact->isFirstForCustomer()) {
            $contact->makePrimary();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact added.')]);

        return to_route('tenant.customers.show', $customer);
    }

    public function update(StoreContactRequest $request, Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $this->authorize('update', $customer);

        $contact->update($request->safe()->except('is_primary'));

        if ($request->boolean('is_primary')) {
            $contact->makePrimary();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact updated.')]);

        return to_route('tenant.customers.show', $customer);
    }

    public function setPrimary(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $this->authorize('update', $customer);

        $contact->makePrimary();

        return to_route('tenant.customers.show', $customer);
    }

    public function destroy(Customer $customer, CustomerContact $contact): RedirectResponse
    {
        $this->authorize('update', $customer);

        $wasPrimary = $contact->is_primary;
        $contact->delete();

        // Promote another contact to primary if the primary one was removed.
        if ($wasPrimary && ($next = $customer->contacts()->first()) !== null) {
            $next->makePrimary();
        }

        Inertia::flash('toast', ['type' => 'success', 'message' => __('Contact removed.')]);

        return to_route('tenant.customers.show', $customer);
    }
}

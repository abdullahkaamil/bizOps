<?php

declare(strict_types=1);

namespace App\Domain\CRM\Concerns;

use Illuminate\Support\Facades\DB;

/**
 * Enforces a single `is_primary = true` row per customer for contacts/addresses.
 */
trait ManagesPrimaryFlag
{
    /**
     * Make this the primary record for its customer, demoting any other.
     */
    public function makePrimary(): void
    {
        DB::transaction(function (): void {
            static::query()
                ->where('customer_id', $this->customer_id)
                ->whereKeyNot($this->getKey())
                ->update(['is_primary' => false]);

            $this->forceFill(['is_primary' => true])->save();
        });
    }

    /**
     * True when the customer has no other records of this type yet.
     */
    public function isFirstForCustomer(): bool
    {
        return ! static::query()
            ->where('customer_id', $this->customer_id)
            ->whereKeyNot($this->getKey())
            ->exists();
    }
}

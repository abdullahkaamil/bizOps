<?php

declare(strict_types=1);

namespace App\Domain\Inventory\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when an outbound movement would drive a warehouse balance below zero
 * and the tenant does not permit negative stock.
 */
class InsufficientStock extends RuntimeException
{
    public static function for(string $sku, float $available, float $requested): self
    {
        return new self("Not enough stock for {$sku}: {$available} available, {$requested} requested.");
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['stock' => $this->getMessage()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Quotations\Exceptions;

use App\Domain\Quotations\Enums\QuotationStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when a quotation transition or edit is invalid for its current status
 * (e.g. editing a sent quotation). A 422-class error.
 */
class InvalidQuotationTransition extends RuntimeException
{
    /**
     * @param  array<int, QuotationStatus>  $allowed
     */
    public static function wrongStatus(QuotationStatus $current, array $allowed): self
    {
        $labels = implode(', ', array_map(static fn (QuotationStatus $s): string => $s->label(), $allowed));

        return new self("A quotation that is \"{$current->label()}\" cannot be changed this way (expected: {$labels}).");
    }

    public static function locked(): self
    {
        return new self('This quotation has been sent and can no longer be edited.');
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['quotation' => $this->getMessage()]);
    }
}

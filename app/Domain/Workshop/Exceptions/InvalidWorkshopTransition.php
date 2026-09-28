<?php

declare(strict_types=1);

namespace App\Domain\Workshop\Exceptions;

use App\Domain\Workshop\Enums\WorkshopStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when a workshop transition is invalid for the ticket's current state.
 * A 422-class error, distinct from an authorization failure.
 */
class InvalidWorkshopTransition extends RuntimeException
{
    /**
     * @param  array<int, WorkshopStatus>  $allowed
     */
    public static function wrongStatus(WorkshopStatus $current, array $allowed): self
    {
        $labels = implode(', ', array_map(static fn (WorkshopStatus $s): string => $s->label(), $allowed));

        return new self("A ticket that is \"{$current->label()}\" cannot be moved this way (expected: {$labels}).");
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['workshop' => $this->getMessage()]);
    }
}

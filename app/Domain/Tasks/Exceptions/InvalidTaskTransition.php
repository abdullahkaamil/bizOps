<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Exceptions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when a task move is structurally invalid — e.g. the destination column
 * belongs to a different board. This is distinct from an authorization failure
 * (403); it is a 422-class error that flashes back rather than 500-ing.
 */
class InvalidTaskTransition extends RuntimeException
{
    public static function differentBoard(): self
    {
        return new self('A task can only be moved between columns on its own board.');
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['task' => $this->getMessage()]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Exceptions;

use App\Domain\Jobs\Enums\JobStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Raised when a job transition is structurally invalid for the job's current
 * state. A 422-class error, distinct from an authorization failure.
 */
class InvalidJobTransition extends RuntimeException
{
    /**
     * @param  array<int, JobStatus>  $allowed
     */
    public static function wrongStatus(JobStatus $current, array $allowed): self
    {
        $labels = implode(', ', array_map(static fn (JobStatus $s): string => $s->label(), $allowed));

        return new self("A job that is \"{$current->label()}\" cannot be moved this way (expected: {$labels}).");
    }

    public static function alreadyStarted(): self
    {
        return new self('This job has already been started.');
    }

    public function render(Request $request): RedirectResponse
    {
        return back()->withErrors(['job' => $this->getMessage()]);
    }
}

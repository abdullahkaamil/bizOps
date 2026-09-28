<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * Fired after a job is completed and committed. Downstream listeners (reporting,
 * customer confirmation, workshop hooks) react to this.
 */
class JobCompleted
{
    use Dispatchable;

    public function __construct(
        public int $jobId,
        public int $actorId,
        public ?int $durationMinutes = null,
    ) {}
}

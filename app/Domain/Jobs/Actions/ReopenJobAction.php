<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Actions\Concerns\RecordsJobTransition;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Models\User;

/**
 * Reopen a completed or canceled job back to in-progress, clearing the terminal
 * timestamps. A privileged, permissioned action (see JobPolicy::reopen).
 */
class ReopenJobAction
{
    use RecordsJobTransition;

    public function handle(Job $job, User $actor, ?string $reason = null): Job
    {
        $this->guardStatus($job, JobStatus::Completed, JobStatus::Canceled);

        return $this->transition(
            $job,
            $actor,
            JobStatus::InProgress,
            attributes: ['actual_end_at' => null, 'completed_by' => null, 'canceled_by' => null],
            metadata: array_filter(['reason' => $reason]),
        );
    }
}

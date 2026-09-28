<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Actions\Concerns\RecordsJobTransition;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Models\User;

/**
 * Cancel a job that has not yet finished. The reason is recorded on the history
 * row. (Cancellation is the recommended operational addition to the client's
 * pending/in-progress/completed set — see the decision register.)
 */
class CancelJobAction
{
    use RecordsJobTransition;

    public function handle(Job $job, User $actor, ?string $reason = null): Job
    {
        $this->guardStatus($job, JobStatus::Pending, JobStatus::InProgress);

        return $this->transition(
            $job,
            $actor,
            JobStatus::Canceled,
            attributes: ['canceled_by' => $actor->id],
            metadata: array_filter(['reason' => $reason]),
        );
    }
}

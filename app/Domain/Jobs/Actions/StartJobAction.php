<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Actions\Concerns\RecordsJobTransition;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Models\User;

/**
 * Start a job. The start time is always taken from the SERVER clock (never the
 * client), the job moves to in-progress, and repeat starts are rejected because
 * only a pending job may be started.
 */
class StartJobAction
{
    use RecordsJobTransition;

    public function handle(Job $job, User $actor): Job
    {
        $this->guardStatus($job, JobStatus::Pending);

        return $this->transition(
            $job,
            $actor,
            JobStatus::InProgress,
            attributes: ['actual_start_at' => now()],
        );
    }
}

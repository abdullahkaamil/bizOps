<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Exceptions\InvalidJobTransition;
use App\Domain\Jobs\Models\Job;
use App\Models\User;

/**
 * Update on-site service/internal notes. Allowed while the job is still open
 * (pending or in progress) — completed and canceled jobs are read-only.
 */
class UpdateJobServiceDataAction
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Job $job, User $actor, array $data): Job
    {
        if ($job->status->isTerminal()) {
            throw InvalidJobTransition::wrongStatus($job->status, [JobStatus::Pending, JobStatus::InProgress]);
        }

        $job->update([
            'service_notes' => $data['service_notes'] ?? $job->service_notes,
            'internal_notes' => $data['internal_notes'] ?? $job->internal_notes,
        ]);

        activity('job')->performedOn($job)->causedBy($actor)->event('service_data_updated')->log('job.service_data_updated');

        return $job->refresh();
    }
}

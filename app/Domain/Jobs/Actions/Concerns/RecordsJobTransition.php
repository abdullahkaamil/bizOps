<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions\Concerns;

use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Exceptions\InvalidJobTransition;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\JobStatusHistory;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Shared machinery for job transition actions: validates the current state, then
 * applies the change atomically while appending an immutable history row and an
 * activity-log entry.
 */
trait RecordsJobTransition
{
    protected function guardStatus(Job $job, JobStatus ...$allowed): void
    {
        if (! in_array($job->status, $allowed, true)) {
            throw InvalidJobTransition::wrongStatus($job->status, array_values($allowed));
        }
    }

    /**
     * @param  array<string, mixed>  $attributes  extra columns to set on the job
     * @param  array<string, mixed>  $metadata  recorded on the history row
     * @param  callable():void|null  $afterCommit
     */
    protected function transition(
        Job $job,
        User $actor,
        JobStatus $to,
        array $attributes = [],
        array $metadata = [],
        ?callable $afterCommit = null,
    ): Job {
        $from = $job->status;

        DB::transaction(function () use ($job, $actor, $from, $to, $attributes, $metadata, $afterCommit): void {
            $job->fill($attributes);
            $job->status = $to;
            $job->save();

            JobStatusHistory::create([
                'job_id' => $job->id,
                'from_status' => $from,
                'to_status' => $to,
                'actor_id' => $actor->id,
                'metadata' => $metadata === [] ? null : $metadata,
                'created_at' => now(),
            ]);

            activity('job')
                ->performedOn($job)
                ->causedBy($actor)
                ->withProperties(['from' => $from->value, 'to' => $to->value])
                ->event($to->value)
                ->log("job.{$from->value}_to_{$to->value}");

            if ($afterCommit !== null) {
                DB::afterCommit($afterCommit);
            }
        });

        return $job->refresh();
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Models\Job;
use App\Domain\Notifications\Notifications\JobAssignedNotification;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * (Re)assign a job's technician. Assignment is not a status change; it is logged
 * and the new technician is notified after commit.
 */
class AssignJobAction
{
    public function handle(Job $job, User $assignee, User $actor): Job
    {
        DB::transaction(function () use ($job, $assignee, $actor): void {
            $job->update(['assigned_user_id' => $assignee->id]);

            activity('job')->performedOn($job)->causedBy($actor)
                ->withProperties(['assigned_user_id' => $assignee->id])
                ->event('assigned')->log('job.assigned');

            DB::afterCommit(fn () => Notification::send($assignee, JobAssignedNotification::for($job)));
        });

        return $job->refresh();
    }
}

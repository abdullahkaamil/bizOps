<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Documents\NextDocumentNumberService;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Models\Job;
use App\Domain\Jobs\Models\JobStatusHistory;
use App\Domain\Notifications\Notifications\JobAssignedNotification;
use App\Domain\Settings\TenantSettings;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Create a job with a unique sequential number and record its initial state.
 * Notifies the assignee if one is set at creation.
 */
class CreateJobAction
{
    public function __construct(
        private NextDocumentNumberService $numbers,
        private TenantSettings $settings,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function handle(array $attributes, User $creator): Job
    {
        $job = DB::transaction(function () use ($attributes, $creator): Job {
            $number = $this->numbers->next('job', $this->settings->jobNumberPrefix());

            $job = Job::create([
                ...$attributes,
                'number' => $number,
                'status' => JobStatus::Pending,
                'created_by' => $creator->id,
            ]);

            JobStatusHistory::create([
                'job_id' => $job->id,
                'from_status' => null,
                'to_status' => JobStatus::Pending,
                'actor_id' => $creator->id,
                'created_at' => now(),
            ]);

            activity('job')->performedOn($job)->causedBy($creator)->event('created')->log('job.created');

            if ($job->assigned_user_id !== null) {
                DB::afterCommit(fn () => $this->notifyAssignee($job));
            }

            return $job;
        });

        return $job->refresh();
    }

    private function notifyAssignee(Job $job): void
    {
        $assignee = User::find($job->assigned_user_id);

        if ($assignee !== null) {
            Notification::send($assignee, JobAssignedNotification::for($job));
        }
    }
}

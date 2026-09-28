<?php

declare(strict_types=1);

namespace App\Domain\Jobs\Actions;

use App\Domain\Jobs\Actions\Concerns\RecordsJobTransition;
use App\Domain\Jobs\Enums\JobStatus;
use App\Domain\Jobs\Events\JobCompleted;
use App\Domain\Jobs\Models\Job;
use App\Domain\Settings\TenantSettings;
use App\Domain\Workshop\Enums\WorkshopStatus;
use App\Domain\Workshop\Models\WorkshopTicket;
use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Complete a job. Enforces the tenant's completion requirements (service notes,
 * signature, photos, and — once Workshop exists — linked workshop constraints)
 * before recording the server end time and the server-computed duration.
 */
class CompleteJobAction
{
    use RecordsJobTransition;

    public function __construct(private TenantSettings $settings) {}

    public function handle(Job $job, User $actor): Job
    {
        $this->guardStatus($job, JobStatus::InProgress);
        $this->assertRequirementsMet($job);
        $this->assertWorkshopConstraints($job);

        $endedAt = now();
        $durationMinutes = $job->actual_start_at !== null
            ? (int) $job->actual_start_at->diffInMinutes($endedAt)
            : null;

        return $this->transition(
            $job,
            $actor,
            JobStatus::Completed,
            attributes: ['actual_end_at' => $endedAt, 'completed_by' => $actor->id],
            metadata: ['duration_minutes' => $durationMinutes],
            afterCommit: fn () => JobCompleted::dispatch($job->id, $actor->id, $durationMinutes),
        );
    }

    private function assertRequirementsMet(Job $job): void
    {
        $errors = [];

        $notes = trim((string) $job->service_notes);
        if (mb_strlen($notes) < $this->settings->jobsMinServiceNotes()) {
            $errors[] = __('Service notes are required before completing this job.');
        }

        if ($this->settings->jobsRequireSignature() && ! $job->signatures()->exists()) {
            $errors[] = __('A customer signature is required before completing this job.');
        }

        if ($this->settings->jobsRequirePhoto() && ! $job->images()->exists()) {
            $errors[] = __('At least one photo is required before completing this job.');
        }

        if ($errors !== []) {
            throw ValidationException::withMessages(['job' => $errors]);
        }
    }

    /**
     * A job linked to a workshop ticket cannot be completed while that ticket is
     * still in progress — the device work must be completed or delivered first.
     */
    private function assertWorkshopConstraints(Job $job): void
    {
        $openTickets = WorkshopTicket::query()
            ->where('job_id', $job->id)
            ->where('status', WorkshopStatus::InProgress->value)
            ->exists();

        if ($openTickets) {
            throw ValidationException::withMessages([
                'job' => __('A linked workshop ticket must be completed before this job can be completed.'),
            ]);
        }
    }
}

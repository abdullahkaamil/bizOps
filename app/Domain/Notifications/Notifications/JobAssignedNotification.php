<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Jobs\Models\Job;
use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to the technician assigned to a field-service job.
 */
class JobAssignedNotification extends TenantNotification
{
    public function __construct(
        public int $jobId,
        public string $jobNumber,
        public string $jobTitle,
        public string $jobPublicId,
        ?string $correlationId = null,
    ) {
        parent::__construct($correlationId);
    }

    public static function for(Job $job): self
    {
        return new self($job->id, $job->number, $job->title, $job->public_id);
    }

    public function type(): NotificationType
    {
        return NotificationType::JobAssigned;
    }

    public function relatedType(): ?string
    {
        return Job::class;
    }

    public function relatedId(): ?int
    {
        return $this->jobId;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('You were assigned a job'))
            ->line(__('You have been assigned job :number: “:title”.', ['number' => $this->jobNumber, 'title' => $this->jobTitle]))
            ->action(__('Open job'), url("/jobs/{$this->jobPublicId}"));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [
            'job_id' => $this->jobPublicId,
            'number' => $this->jobNumber,
            'title' => $this->jobTitle,
            'link' => "/jobs/{$this->jobPublicId}",
        ];
    }
}

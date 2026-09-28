<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Tasks\Models\Task;

/**
 * Shared base for task-related notifications. Carries scalar task data (not the
 * Eloquent model) so the queued payload stays small and tenant-independent; the
 * related record is still recorded for the email log and in-app payload.
 */
abstract class TaskNotification extends TenantNotification
{
    public function __construct(
        public int $taskId,
        public string $taskTitle,
        public string $taskPublicId,
        public string $boardPublicId,
        ?string $correlationId = null,
    ) {
        parent::__construct($correlationId);
    }

    public function relatedType(): ?string
    {
        return Task::class;
    }

    public function relatedId(): ?int
    {
        return $this->taskId;
    }

    protected function link(): string
    {
        return "/boards/{$this->boardPublicId}?task={$this->taskPublicId}";
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [
            'task_id' => $this->taskPublicId,
            'task_title' => $this->taskTitle,
            'board_id' => $this->boardPublicId,
            'link' => $this->link(),
        ];
    }
}

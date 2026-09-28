<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Broad status-change notice sent to every board member (except the actor) when a
 * task moves between steps — forward or backward.
 */
class TaskMovedNotification extends TaskNotification
{
    public function __construct(
        int $taskId,
        string $taskTitle,
        string $taskPublicId,
        string $boardPublicId,
        public string $columnName,
        ?string $correlationId = null,
    ) {
        parent::__construct($taskId, $taskTitle, $taskPublicId, $boardPublicId, $correlationId);
    }

    public function type(): NotificationType
    {
        return NotificationType::TaskMoved;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('A task moved'))
            ->line(__('“:title” moved to the “:column” step.', ['title' => $this->taskTitle, 'column' => $this->columnName]))
            ->action(__('View task'), url($this->link()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [...parent::arrayData($notifiable), 'column' => $this->columnName];
    }
}

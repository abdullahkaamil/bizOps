<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Targeted alert: a task has entered a step that only *your* side may move it out
 * of, so the ball is in your court (e.g. the customer's "your approval is needed"
 * mail when work reaches the review step).
 */
class TaskActionNeededNotification extends TaskNotification
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
        return NotificationType::TaskActionNeeded;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('A task needs your action'))
            ->line(__('“:title” has reached the “:column” step and is waiting on you.', [
                'title' => $this->taskTitle,
                'column' => $this->columnName,
            ]))
            ->action(__('Take action'), url($this->link()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [...parent::arrayData($notifiable), 'column' => $this->columnName];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent to every member of a project board (internal and external) when a new task
 * is opened, so the whole project is kept in the loop.
 */
class TaskCreatedNotification extends TaskNotification
{
    public function __construct(
        int $taskId,
        string $taskTitle,
        string $taskPublicId,
        string $boardPublicId,
        public string $boardName,
        ?string $correlationId = null,
    ) {
        parent::__construct($taskId, $taskTitle, $taskPublicId, $boardPublicId, $correlationId);
    }

    public function type(): NotificationType
    {
        return NotificationType::TaskCreated;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('New activity on “:board”', ['board' => $this->boardName]))
            ->line(__('A new task “:title” has been opened.', ['title' => $this->taskTitle]))
            ->action(__('Open task'), url($this->link()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [...parent::arrayData($notifiable), 'board_name' => $this->boardName];
    }
}

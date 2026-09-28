<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class TaskDueSoonNotification extends TaskNotification
{
    public function __construct(
        int $taskId,
        string $taskTitle,
        string $taskPublicId,
        string $boardPublicId,
        public ?string $dueAt = null,
        ?string $correlationId = null,
    ) {
        parent::__construct($taskId, $taskTitle, $taskPublicId, $boardPublicId, $correlationId);
    }

    public function type(): NotificationType
    {
        return NotificationType::TaskDueSoon;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('A task is due soon'))
            ->line(__('“:title” is due soon.', ['title' => $this->taskTitle]))
            ->action(__('View task'), url($this->link()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [
            ...parent::arrayData($notifiable),
            'due_at' => $this->dueAt,
        ];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

class TaskAssignedNotification extends TaskNotification
{
    public function type(): NotificationType
    {
        return NotificationType::TaskAssigned;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('You were assigned a task'))
            ->line(__('You have been assigned to “:title”.', ['title' => $this->taskTitle]))
            ->action(__('View task'), url($this->link()));
    }
}

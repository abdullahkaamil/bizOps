<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Sent (email only) to an invited email address, which is not yet a user. Uses
 * the on-demand notifiable path.
 */
class UserInvitedNotification extends TenantNotification
{
    public function __construct(
        public string $acceptUrl,
        public string $roleLabel,
        ?string $correlationId = null,
    ) {
        parent::__construct($correlationId);
    }

    public function type(): NotificationType
    {
        return NotificationType::UserInvited;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return $this->brandedMail(__('You have been invited'))
            ->line(__('You have been invited to join as :role.', ['role' => $this->roleLabel]))
            ->action(__('Accept invitation'), $this->acceptUrl)
            ->line(__('This invitation will expire.'));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [
            'role' => $this->roleLabel,
            'accept_url' => $this->acceptUrl,
        ];
    }
}

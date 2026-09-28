<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Enums\EmailStatus;
use App\Domain\Notifications\Models\EmailLog;
use App\Domain\Notifications\Notifications\TenantNotification;
use Illuminate\Mail\SentMessage;
use Illuminate\Notifications\Channels\MailChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Throwable;

/**
 * Sends the notification's mail exactly like Laravel's MailChannel, but records
 * an EmailLog row on both success and failure so delivery is auditable per
 * tenant. Failures are re-thrown so the queue can mark the job failed/retry —
 * the tenant id and correlation id survive on the failed job payload.
 */
class TenantMailChannel extends MailChannel
{
    public function send($notifiable, Notification $notification): ?SentMessage
    {
        $message = method_exists($notification, 'toMail') ? $notification->toMail($notifiable) : null;
        $subject = $message instanceof MailMessage ? $message->subject : null;
        $recipient = $this->recipient($notifiable, $notification);

        try {
            $sent = parent::send($notifiable, $notification);
            $this->log($notification, $recipient, $subject, EmailStatus::Sent, null);

            return $sent;
        } catch (Throwable $e) {
            $this->log($notification, $recipient, $subject, EmailStatus::Failed, $e->getMessage());

            throw $e;
        }
    }

    protected function recipient(object $notifiable, Notification $notification): string
    {
        $route = method_exists($notifiable, 'routeNotificationFor')
            ? $notifiable->routeNotificationFor('mail', $notification)
            : null;

        if (is_array($route)) {
            $first = array_key_first($route);

            return is_string($first) ? $first : (string) reset($route);
        }

        return is_scalar($route) ? (string) $route : '';
    }

    protected function log(
        Notification $notification,
        string $recipient,
        ?string $subject,
        EmailStatus $status,
        ?string $error,
    ): void {
        $type = $notification instanceof TenantNotification
            ? $notification->type()->value
            : $notification::class;

        EmailLog::create([
            'notification_type' => $type,
            'recipient' => $recipient,
            'subject' => $subject,
            'status' => $status,
            'error_message' => $error,
            'correlation_id' => $notification instanceof TenantNotification ? $notification->correlationId() : null,
            'related_type' => $notification instanceof TenantNotification ? $notification->relatedType() : null,
            'related_id' => $notification instanceof TenantNotification ? $notification->relatedId() : null,
            'sent_at' => $status === EmailStatus::Sent ? now() : null,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Channels;

use App\Domain\Notifications\Notifications\TenantNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;
use Ramsey\Uuid\Uuid;

/**
 * Stores the in-app (database) notification under a DETERMINISTIC id derived from
 * the correlation id + type + related record + recipient. A retried queue job
 * therefore updates the same row instead of creating a duplicate in-app entry.
 */
class IdempotentDatabaseChannel extends DatabaseChannel
{
    public function send($notifiable, Notification $notification)
    {
        $id = $this->deterministicId($notifiable, $notification);
        $notification->id = $id;

        return $notifiable->routeNotificationFor('database', $notification)->updateOrCreate(
            ['id' => $id],
            [
                'type' => method_exists($notification, 'databaseType')
                    ? $notification->databaseType($notifiable)
                    : $notification::class,
                'data' => $this->getData($notifiable, $notification),
                'read_at' => null,
            ],
        );
    }

    protected function deterministicId(object $notifiable, Notification $notification): string
    {
        if (! $notification instanceof TenantNotification) {
            return (string) Uuid::uuid4();
        }

        $name = implode('|', [
            $notification->correlationId(),
            $notification->type()->value,
            $notification->relatedType() ?? '',
            (string) ($notification->relatedId() ?? ''),
            method_exists($notifiable, 'getMorphClass') ? $notifiable->getMorphClass() : $notifiable::class,
            (string) (method_exists($notifiable, 'getKey') ? $notifiable->getKey() : ''),
        ]);

        return Uuid::uuid5(Uuid::NAMESPACE_OID, $name)->toString();
    }
}

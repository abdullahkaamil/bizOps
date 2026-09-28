<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Notifications\Services\NotificationPreferences;
use App\Domain\Notifications\Support\CorrelationId;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * Base for every platform notification. Handles the cross-cutting concerns so a
 * module only supplies its content: queued delivery after the DB commit, channel
 * selection via user preferences, tenant email branding, and a correlation id
 * that is carried onto the queue (and preserved on failed jobs alongside the
 * tenant id serialized by stancl's QueueTenancyBootstrapper).
 */
abstract class TenantNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $correlationId;

    public function __construct(?string $correlationId = null)
    {
        $this->correlationId = $correlationId ?? CorrelationId::current();

        // Only release the queued job once the surrounding transaction commits.
        $this->afterCommit();
    }

    abstract public function type(): NotificationType;

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function relatedType(): ?string
    {
        return null;
    }

    public function relatedId(): ?int
    {
        return null;
    }

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        $channels = [];

        if ($notifiable instanceof User) {
            $prefs = app(NotificationPreferences::class);

            if ($prefs->inAppEnabled($notifiable, $this->type())) {
                $channels[] = 'tenant-database';
            }
            if ($prefs->emailEnabled($notifiable, $this->type())) {
                $channels[] = 'tenant-mail';
            }

            return $channels;
        }

        // On-demand notifiables (e.g. invitations to a not-yet-user email) get email only.
        return ['tenant-mail'];
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => $this->type()->value,
            ...$this->arrayData($notifiable),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    abstract protected function arrayData(object $notifiable): array;

    /**
     * A MailMessage pre-branded with the tenant's name in the subject and greeting.
     */
    protected function brandedMail(string $subject): MailMessage
    {
        $brand = $this->brand();

        return (new MailMessage)
            ->subject("{$brand}: {$subject}")
            ->greeting($subject);
    }

    protected function brand(): string
    {
        $name = function_exists('tenant') && tenant() ? tenant('name') : null;

        return is_string($name) && $name !== '' ? $name : (string) config('app.name');
    }
}

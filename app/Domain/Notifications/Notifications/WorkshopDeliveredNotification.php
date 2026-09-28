<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Workshop\Models\WorkshopTicket;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Storage;

/**
 * Sent (email only) to the customer when their device is delivered, with the
 * delivery report PDF attached. Queued + logged via the notification foundation.
 */
class WorkshopDeliveredNotification extends TenantNotification
{
    public function __construct(
        public int $ticketId,
        public string $ticketNumber,
        public string $deviceLabel,
        public string $documentDisk,
        public string $documentPath,
        ?string $correlationId = null,
    ) {
        parent::__construct($correlationId);
    }

    public function type(): NotificationType
    {
        return NotificationType::WorkshopDelivered;
    }

    public function relatedType(): ?string
    {
        return WorkshopTicket::class;
    }

    public function relatedId(): ?int
    {
        return $this->ticketId;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = $this->brandedMail(__('Your device is ready'))
            ->line(__('Your device (:device) has been repaired and delivered.', ['device' => $this->deviceLabel]))
            ->line(__('Reference: :number.', ['number' => $this->ticketNumber]))
            ->line(__('The delivery report is attached.'));

        if (Storage::disk($this->documentDisk)->exists($this->documentPath)) {
            $message->attach(Storage::disk($this->documentDisk)->path($this->documentPath), [
                'as' => $this->ticketNumber.'.pdf',
                'mime' => 'application/pdf',
            ]);
        }

        return $message;
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [
            'ticket' => $this->ticketNumber,
            'device' => $this->deviceLabel,
        ];
    }
}

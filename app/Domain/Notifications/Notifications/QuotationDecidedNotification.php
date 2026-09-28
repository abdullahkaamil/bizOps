<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Notifications;

use App\Domain\Notifications\Enums\NotificationType;
use App\Domain\Quotations\Models\Quotation;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Tells the internal owner of a quotation that the customer accepted or rejected
 * it through the public link.
 */
class QuotationDecidedNotification extends TenantNotification
{
    public function __construct(
        public int $quotationId,
        public string $quotationNumber,
        public string $quotationPublicId,
        public bool $accepted,
        public string $customerName,
        ?string $correlationId = null,
    ) {
        parent::__construct($correlationId);
    }

    public function type(): NotificationType
    {
        return NotificationType::QuotationDecided;
    }

    public function relatedType(): ?string
    {
        return Quotation::class;
    }

    public function relatedId(): ?int
    {
        return $this->quotationId;
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->accepted
            ? __('Quotation :number was accepted', ['number' => $this->quotationNumber])
            : __('Quotation :number was rejected', ['number' => $this->quotationNumber]);

        return $this->brandedMail($subject)
            ->line(__(':name responded to quotation :number.', ['name' => $this->customerName, 'number' => $this->quotationNumber]))
            ->action(__('View quotation'), url($this->link()));
    }

    /**
     * @return array<string, mixed>
     */
    protected function arrayData(object $notifiable): array
    {
        return [
            'quotation_id' => $this->quotationPublicId,
            'number' => $this->quotationNumber,
            'accepted' => $this->accepted,
            'customer' => $this->customerName,
            'link' => $this->link(),
        ];
    }

    protected function link(): string
    {
        return "/quotations/{$this->quotationPublicId}";
    }
}

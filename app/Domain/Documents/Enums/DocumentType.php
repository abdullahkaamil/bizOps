<?php

declare(strict_types=1);

namespace App\Domain\Documents\Enums;

enum DocumentType: string
{
    case JobServiceReport = 'job_service_report';
    case WorkshopCompletionReport = 'workshop_completion_report';
    case WorkshopDeliveryReport = 'workshop_delivery_report';
    case Quotation = 'quotation';
    case CustomerExport = 'customer_export';

    public function label(): string
    {
        return match ($this) {
            self::JobServiceReport => __('Service report'),
            self::WorkshopCompletionReport => __('Workshop completion report'),
            self::WorkshopDeliveryReport => __('Workshop delivery report'),
            self::Quotation => __('Quotation'),
            self::CustomerExport => __('Customer export'),
        };
    }

    /**
     * Whether an external customer representative may ever download this type.
     */
    public function isCustomerFacing(): bool
    {
        return match ($this) {
            self::Quotation, self::CustomerExport => true,
            default => false,
        };
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $t): string => $t->value, self::cases());
    }
}

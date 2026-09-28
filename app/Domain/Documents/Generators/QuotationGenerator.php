<?php

declare(strict_types=1);

namespace App\Domain\Documents\Generators;

use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Support\DocumentContext;
use App\Domain\Quotations\Models\Quotation;
use App\Domain\Quotations\Models\QuotationLine;
use App\Support\Money;

/**
 * Customer-facing quotation PDF. Deliberately shows only the CUSTOMER ALIAS for
 * each line — never the internal item name or cost.
 */
class QuotationGenerator extends AbstractDocumentGenerator
{
    public function type(): DocumentType
    {
        return DocumentType::Quotation;
    }

    protected function view(): string
    {
        return 'documents.quotation';
    }

    protected function number(DocumentContext $context): ?string
    {
        return $this->quotation($context)->number;
    }

    /**
     * @return array<string, mixed>
     */
    protected function metadata(DocumentContext $context): array
    {
        return ['quotation' => $this->quotation($context)->public_id];
    }

    /**
     * @return array<string, mixed>
     */
    protected function viewData(DocumentContext $context): array
    {
        $quotation = $this->quotation($context);
        $quotation->loadMissing(['customer', 'contact', 'lines']);
        $settings = $context->settings;

        return [
            'quotation' => $quotation,
            'number' => $quotation->number,
            'date' => $settings->formatDateTime(now()),
            'issue_date' => $quotation->issue_date->format($settings->dateFormat()),
            'valid_until' => $quotation->valid_until?->format($settings->dateFormat()),
            'currency' => $quotation->currency,
            'customer' => $quotation->customer,
            'contact' => $quotation->contact,
            'notes' => $quotation->notes,
            'terms' => $quotation->terms,
            'lines' => $quotation->lines->map(fn (QuotationLine $l): array => [
                'alias' => $l->customer_alias,
                'description' => $l->description,
                'quantity' => rtrim(rtrim($l->quantity, '0'), '.'),
                'unit' => $l->unit,
                'unit_price' => Money::toMajor($l->unit_price),
                'tax_rate' => rtrim(rtrim($l->tax_rate, '0'), '.'),
                'line_total' => Money::toMajor($l->line_total),
            ])->all(),
            'subtotal' => Money::toMajor($quotation->subtotal),
            'discount_total' => Money::toMajor($quotation->discount_total),
            'tax_total' => Money::toMajor($quotation->tax_total),
            'grand_total' => Money::toMajor($quotation->grand_total),
        ];
    }

    private function quotation(DocumentContext $context): Quotation
    {
        /** @var Quotation $quotation */
        $quotation = $context->related;

        return $quotation;
    }
}

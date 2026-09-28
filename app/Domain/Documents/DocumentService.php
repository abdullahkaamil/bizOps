<?php

declare(strict_types=1);

namespace App\Domain\Documents;

use App\Domain\Documents\Contracts\DocumentGenerator;
use App\Domain\Documents\Enums\DocumentType;
use App\Domain\Documents\Generators\JobServiceReportGenerator;
use App\Domain\Documents\Generators\QuotationGenerator;
use App\Domain\Documents\Generators\WorkshopCompletionReportGenerator;
use App\Domain\Documents\Generators\WorkshopDeliveryReportGenerator;
use App\Domain\Documents\Jobs\GenerateDocument;
use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Support\DocumentContext;
use App\Domain\Settings\TenantSettings;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;

/**
 * Entry point for document generation. Resolves the per-type generator and can
 * run it inline or push it onto the queue for large documents.
 */
class DocumentService
{
    /**
     * @var array<string, class-string<DocumentGenerator>>
     */
    protected const GENERATORS = [
        DocumentType::JobServiceReport->value => JobServiceReportGenerator::class,
        DocumentType::WorkshopCompletionReport->value => WorkshopCompletionReportGenerator::class,
        DocumentType::WorkshopDeliveryReport->value => WorkshopDeliveryReportGenerator::class,
        DocumentType::Quotation->value => QuotationGenerator::class,
        // Customer-export generator lands with its module.
    ];

    public function generate(DocumentType $type, Model $related, ?User $actor = null): GeneratedDocument
    {
        $generator = app($this->generatorClass($type));

        $context = new DocumentContext($type, $related, $actor, app(TenantSettings::class));

        return $generator->generate($context);
    }

    /**
     * Queue generation for large documents (runs in the correct tenant context).
     */
    public function queue(DocumentType $type, Model $related, ?User $actor = null): void
    {
        GenerateDocument::dispatch($type->value, $related::class, (int) $related->getKey(), $actor?->id);
    }

    public function supports(DocumentType $type): bool
    {
        return isset(self::GENERATORS[$type->value]);
    }

    /**
     * @return class-string<DocumentGenerator>
     */
    protected function generatorClass(DocumentType $type): string
    {
        if (! isset(self::GENERATORS[$type->value])) {
            throw new InvalidArgumentException("No generator registered for document type [{$type->value}].");
        }

        return self::GENERATORS[$type->value];
    }
}

<?php

declare(strict_types=1);

namespace App\Domain\Documents\Generators;

use App\Domain\Documents\Enums\DocumentType;

class WorkshopCompletionReportGenerator extends WorkshopReportGenerator
{
    public function type(): DocumentType
    {
        return DocumentType::WorkshopCompletionReport;
    }

    protected function variant(): string
    {
        return 'completion';
    }
}

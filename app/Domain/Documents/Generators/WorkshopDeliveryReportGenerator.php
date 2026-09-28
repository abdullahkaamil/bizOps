<?php

declare(strict_types=1);

namespace App\Domain\Documents\Generators;

use App\Domain\Documents\Enums\DocumentType;

class WorkshopDeliveryReportGenerator extends WorkshopReportGenerator
{
    public function type(): DocumentType
    {
        return DocumentType::WorkshopDeliveryReport;
    }

    protected function variant(): string
    {
        return 'delivery';
    }
}

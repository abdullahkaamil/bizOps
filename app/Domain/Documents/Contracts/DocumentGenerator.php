<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

use App\Domain\Documents\Models\GeneratedDocument;
use App\Domain\Documents\Support\DocumentContext;

/**
 * Produces one kind of document. Implemented per document type (never one giant
 * conditional template).
 */
interface DocumentGenerator
{
    public function generate(DocumentContext $context): GeneratedDocument;
}

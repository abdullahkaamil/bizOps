<?php

declare(strict_types=1);

namespace App\Domain\Documents\Contracts;

/**
 * Renders an HTML string to PDF bytes. Swappable behind this interface so the
 * engine (dompdf today) is an implementation detail.
 */
interface PdfRenderer
{
    public function render(string $html, string $paper = 'a4', string $orientation = 'portrait'): string;
}

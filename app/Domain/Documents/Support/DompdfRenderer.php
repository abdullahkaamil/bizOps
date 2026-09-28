<?php

declare(strict_types=1);

namespace App\Domain\Documents\Support;

use App\Domain\Documents\Contracts\PdfRenderer;
use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Pure-PHP PDF rendering via dompdf. Remote resource loading is disabled — assets
 * (logo, photos, signature) must be embedded as data: URIs by the generator, so
 * nothing private is fetched over the network.
 */
class DompdfRenderer implements PdfRenderer
{
    public function render(string $html, string $paper = 'a4', string $orientation = 'portrait'): string
    {
        $options = new Options;
        $options->set('isRemoteEnabled', false);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper($paper, $orientation);
        $dompdf->render();

        return (string) $dompdf->output();
    }
}

<?php

namespace App\Services\Pdf;

use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DompdfDocument;

/**
 * Central Dompdf entry point: Arabic shaping + consistent PDF options.
 */
class PdfFactory
{
    public function __construct(
        private readonly ArabicHtmlShaper $shaper,
    ) {}

    public function loadHtml(string $html): DompdfDocument
    {
        $document = Pdf::loadHTML($this->shaper->shape($html));
        $document->setPaper('a4', 'portrait');
        $document->setOption('isHtml5ParserEnabled', true);
        $document->setOption('isRemoteEnabled', false);
        $document->setOption('defaultFont', 'DejaVu Sans');

        return $document;
    }
}

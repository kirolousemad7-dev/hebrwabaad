<?php

namespace App\Services\Pdf;

use ArPHP\I18N\Arabic;

/**
 * Preprocesses HTML for Dompdf so Arabic text is glyph-shaped and readable.
 *
 * Dompdf does not implement Arabic complex text layout (joining / bidi).
 * Ar-PHP converts logical Arabic into presentation-form glyphs that DejaVu Sans can paint.
 */
class ArabicHtmlShaper
{
    /**
     * Max characters per soft wrap inside utf8Glyphs. Keep high so table cells
     * are not aggressively re-wrapped mid-phrase.
     */
    private const MAX_CHARS = 600;

    public function shape(string $html): string
    {
        if ($html === '' || ! preg_match('/\p{Arabic}/u', $html)) {
            return $html;
        }

        $arabic = new Arabic;
        $segments = $arabic->arIdentify($html);

        for ($i = count($segments) - 1; $i >= 1; $i -= 2) {
            $start = $segments[$i - 1];
            $end = $segments[$i];
            $length = $end - $start;

            if ($length <= 0) {
                continue;
            }

            $slice = substr($html, $start, $length);
            // hindo=false keeps Western digits (prices, phones) intact.
            $shaped = $arabic->utf8Glyphs($slice, self::MAX_CHARS, false, false);
            $html = substr_replace($html, $shaped, $start, $length);
        }

        return $html;
    }
}

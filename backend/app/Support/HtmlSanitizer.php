<?php

namespace App\Support;

/**
 * Strict HTML allow-list sanitizer for CMS rich content.
 * Strips scripts, event handlers, javascript: URLs, and unsafe iframes.
 */
final class HtmlSanitizer
{
    /**
     * @var list<string>
     */
    private const ALLOWED_TAGS = [
        'h1', 'h2', 'h3', 'p', 'br', 'strong', 'b', 'em', 'i',
        'ul', 'ol', 'li', 'a', 'blockquote', 'hr',
    ];

    public static function clean(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta)[^>]*>.*?</\1>#is', '', $html) ?? '';
        $html = preg_replace('#<(script|style|iframe|object|embed|form|input|button|textarea|select|link|meta)[^>]*/?>#is', '', $html) ?? '';

        $allowed = '<'.implode('><', self::ALLOWED_TAGS).'>';
        $html = strip_tags($html, $allowed);

        $previous = libxml_use_internal_errors(true);
        $document = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="UTF-8"><div id="hebr-sanitize-root">'.$html.'</div>';
        $document->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('hebr-sanitize-root');
        if ($root === null) {
            return trim(strip_tags($html, $allowed));
        }

        foreach (iterator_to_array($root->childNodes) as $child) {
            self::scrubNode($child);
        }

        $output = '';
        foreach (iterator_to_array($root->childNodes) as $child) {
            $output .= $document->saveHTML($child) ?: '';
        }

        return trim($output);
    }

    private static function scrubNode(\DOMNode $node): void
    {
        if ($node instanceof \DOMElement) {
            $tag = strtolower($node->tagName);
            if (! in_array($tag, self::ALLOWED_TAGS, true)) {
                $parent = $node->parentNode;
                if ($parent !== null) {
                    while ($node->firstChild) {
                        $parent->insertBefore($node->firstChild, $node);
                    }
                    $parent->removeChild($node);
                }

                return;
            }

            $removeAttrs = [];
            if ($node->hasAttributes()) {
                /** @var \DOMAttr $attr */
                foreach (iterator_to_array($node->attributes) as $attr) {
                    $name = strtolower($attr->name);
                    $value = trim($attr->value);

                    if (str_starts_with($name, 'on')) {
                        $removeAttrs[] = $attr->name;

                        continue;
                    }

                    if ($tag === 'a' && $name === 'href') {
                        if ($value === '' || preg_match('/^\s*javascript\s*:/i', $value) || preg_match('/^\s*data\s*:/i', $value)) {
                            $removeAttrs[] = $attr->name;

                            continue;
                        }
                        $node->setAttribute('rel', 'noopener noreferrer');

                        continue;
                    }

                    if ($tag === 'a' && in_array($name, ['target', 'title', 'rel'], true)) {
                        continue;
                    }

                    $removeAttrs[] = $attr->name;
                }
            }

            foreach ($removeAttrs as $attrName) {
                $node->removeAttribute($attrName);
            }
        }

        foreach (iterator_to_array($node->childNodes) as $child) {
            self::scrubNode($child);
        }
    }
}

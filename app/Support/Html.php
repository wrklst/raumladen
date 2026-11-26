<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Text that staff write with a little HTML in it (a product description),
 * made safe to put into a page as it is: a short list of harmless elements
 * stays, everything else goes. Scripts, styles and frames are removed with
 * what they contain; any other unknown element leaves its text behind. No
 * attribute survives but the address of a link, and that only when it leads
 * to a web page, a mail address or a place in the shop.
 */
final class Html
{
    private const array ALLOWED = ['p', 'br', 'em', 'i', 'strong', 'b', 'u', 's', 'small', 'sup', 'sub', 'span', 'cite', 'q', 'blockquote', 'ul', 'ol', 'li', 'h2', 'h3', 'h4', 'a'];

    private const array REMOVED_WITH_CONTENT = ['script', 'style', 'iframe', 'object', 'embed', 'svg', 'math', 'template', 'noscript', 'form', 'input', 'button', 'select', 'textarea', 'link', 'meta', 'base', 'audio', 'video', 'img', 'picture', 'source', 'canvas'];

    public static function clean(?string $html): ?string
    {
        if ($html === null || trim($html) === '') {
            return null;
        }

        // Plain text is the common case and needs no parser.
        if (! str_contains($html, '<')) {
            return htmlspecialchars($html, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        }

        $document = new DOMDocument;
        // The prefix makes libxml read UTF-8; the wrapper gives stray text a parent.
        @$document->loadHTML('<?xml encoding="utf-8"?><div>'.$html.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_NONET);

        $root = $document->getElementsByTagName('div')->item(0);

        if (! $root instanceof DOMElement) {
            return htmlspecialchars(strip_tags($html), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
        }

        self::cleanChildren($root);

        $clean = '';

        foreach ($root->childNodes as $child) {
            $clean .= $document->saveHTML($child);
        }

        return trim($clean) === '' ? null : $clean;
    }

    /**
     * The text alone, for places that are not a page: search, feeds, metadata.
     */
    public static function text(?string $html): ?string
    {
        return $html === null ? null : trim(html_entity_decode(strip_tags((string) preg_replace('/<(script|style)\b.*?<\/\1>/is', '', $html)), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private static function cleanChildren(DOMNode $parent): void
    {
        // A copy of the list: the loop changes the children.
        foreach (iterator_to_array($parent->childNodes) as $node) {
            if ($node->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (! $node instanceof DOMElement) {
                // Comments, processing instructions, CDATA.
                $parent->removeChild($node);

                continue;
            }

            $tag = strtolower($node->tagName);

            if (in_array($tag, self::REMOVED_WITH_CONTENT, true)) {
                $parent->removeChild($node);

                continue;
            }

            self::cleanChildren($node);

            if (! in_array($tag, self::ALLOWED, true)) {
                // Unknown element: its (already cleaned) content takes its place.
                while ($node->firstChild !== null) {
                    $parent->insertBefore($node->firstChild, $node);
                }

                $parent->removeChild($node);

                continue;
            }

            $href = $tag === 'a' ? trim($node->getAttribute('href')) : '';

            foreach (iterator_to_array($node->attributes) as $attribute) {
                $node->removeAttribute($attribute->nodeName);
            }

            if ($href !== '' && preg_match('/^(https?:\/\/|mailto:|\/(?!\/)|#)/i', $href) === 1) {
                $node->setAttribute('href', $href);
                $node->setAttribute('rel', 'noopener noreferrer');
            }
        }
    }
}

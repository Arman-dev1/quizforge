<?php

namespace App\Support;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Whitelist sanitizer for rich-text authored in the admin.
 *
 * Outcome descriptions are written by quiz authors and rendered unescaped to
 * respondents, so this is the boundary that stops an author (or anyone who
 * gets hold of an editor session) from shipping script to their audience.
 * Everything not explicitly allowed is unwrapped or dropped.
 */
class HtmlSanitizer
{
    /** Tag => allowed attributes. */
    protected const ALLOWED = [
        'p' => [],
        'br' => [],
        'strong' => [],
        'b' => [],
        'em' => [],
        'i' => [],
        'u' => [],
        's' => [],
        'ul' => [],
        'ol' => [],
        'li' => [],
        'h2' => [],
        'h3' => [],
        'h4' => [],
        'blockquote' => [],
        'a' => ['href', 'target', 'rel'],
        'span' => [],
        'div' => [],
    ];

    /** Schemes a link may use. Excludes javascript:, data:, vbscript:. */
    protected const ALLOWED_SCHEMES = ['http', 'https', 'mailto', 'tel'];

    public function clean(?string $html, int $maxLength = 20000): string
    {
        $html = trim((string) $html);

        if ($html === '') {
            return '';
        }

        $html = mb_substr($html, 0, $maxLength);

        $document = new DOMDocument;

        // Parse as a fragment; suppress warnings about HTML5 tags.
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<?xml encoding="UTF-8"><div id="qf-root">'.$html.'</div>',
            LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $root = $document->getElementById('qf-root');

        if (! $root) {
            return '';
        }

        $this->cleanNode($root);

        $out = '';

        foreach ($root->childNodes as $child) {
            $out .= $document->saveHTML($child);
        }

        return trim($out);
    }

    /** Recursively strip anything outside the whitelist. */
    protected function cleanNode(DOMNode $node): void
    {
        // Snapshot children: the list mutates as we remove nodes.
        foreach (iterator_to_array($node->childNodes) as $child) {
            if ($child instanceof DOMElement) {
                $tag = strtolower($child->tagName);

                if (! array_key_exists($tag, self::ALLOWED)) {
                    // script/style carry no useful text; anything else gets
                    // unwrapped so the author's words survive.
                    if (in_array($tag, ['script', 'style', 'iframe', 'object', 'embed', 'form'], true)) {
                        $child->parentNode?->removeChild($child);
                    } else {
                        $this->cleanNode($child);
                        $this->unwrap($child);
                    }

                    continue;
                }

                $this->cleanAttributes($child, $tag);
                $this->cleanNode($child);

                continue;
            }

            // Comments can hide conditional markup; text nodes are fine.
            if ($child->nodeType === XML_COMMENT_NODE) {
                $child->parentNode?->removeChild($child);
            }
        }
    }

    protected function cleanAttributes(DOMElement $element, string $tag): void
    {
        foreach (iterator_to_array($element->attributes ?? []) as $attribute) {
            $name = strtolower($attribute->nodeName);

            if (! in_array($name, self::ALLOWED[$tag], true)) {
                $element->removeAttribute($attribute->nodeName);

                continue;
            }

            if ($name === 'href' && ! $this->isSafeUrl($attribute->nodeValue)) {
                $element->removeAttribute('href');
            }
        }

        // Any link that survives opens safely.
        if ($tag === 'a' && $element->hasAttribute('href')) {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer nofollow');
        }
    }

    protected function isSafeUrl(?string $url): bool
    {
        $url = trim((string) $url);

        if ($url === '' || str_starts_with($url, '#') || str_starts_with($url, '/')) {
            return true;
        }

        $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));

        return in_array($scheme, self::ALLOWED_SCHEMES, true);
    }

    /** Replace an element with its children. */
    protected function unwrap(DOMElement $element): void
    {
        $parent = $element->parentNode;

        if (! $parent) {
            return;
        }

        while ($element->firstChild) {
            $parent->insertBefore($element->firstChild, $element);
        }

        $parent->removeChild($element);
    }

    /** Plain-text version, for previews and list summaries. */
    public function toText(?string $html, int $limit = 160): string
    {
        $text = trim(html_entity_decode(strip_tags((string) $html), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';

        return mb_strlen($text) > $limit ? mb_substr($text, 0, $limit).'…' : $text;
    }
}

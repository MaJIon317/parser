<?php

namespace App\Services\Parser;

use DOMDocument;
use DOMNodeList;
use DOMXPath;

/*
 * Обёртка над DOMDocument и XPath
 * Обеспечивает удобный доступ к узлам и тексту.
 */
class DomParser
{
    protected DOMDocument $dom;
    protected DOMXPath $xpath;

    public function __construct(string $html)
    {
        libxml_use_internal_errors(true);
        $this->dom = new DOMDocument();
        $this->dom->loadHTML($html);
        $this->xpath = new DOMXPath($this->dom);
    }

    public function query(string $query, $container = null): ?DOMNodeList
    {
        return $query ? $this->xpath->query($query, $container) : null;
    }

    public function text(?string $query, ?string $regex = null): ?string
    {
        if (!$query) {
            return null;
        }

        $node = $this->xpath->query($query)?->item(0);
        if (!$node) {
            return null;
        }

        $text = trim($node->textContent);

        // ✅ Если передан регулярное выражение — применяем его
        if ($regex) {
            if (preg_match($regex, $text, $matches)) {
                return $matches[1] ?? null;
            } else {
                return null;
            }
        }

        return $text;
    }

    public function replace(?string $text, ?string $regex = null): ?string
    {
        if (!$text) {
            return null;
        }

        if ($regex) {
            $text = trim($text);

            if (preg_match($regex, $text, $matches)) {
                return $matches[1] ?? null;
            } else {
                return null;
            }
        }

        return $text;
    }
}

<?php

namespace App\Services\Parser;

use DOMNode;

/*
 * Парсинг характеристик
 * Гибко настраивается через settings.
 */
class ProductSpecParser
{
    protected array $settings;

    public function __construct(array $settings)
    {
        $this->settings = $settings;
    }

    public function parse(DomParser $dom): array
    {
        $specs = [];
        $containers = $dom->query($this->settings['container'] ?? '');

        if (!$containers) return [];

        foreach ($containers as $container) {
            $items = $dom->query($this->settings['item'] ?? './/*', $container);
            if (!$items) continue;

            foreach ($items as $row) {
                $keyNode = $dom->query($this->settings['key'] ?? './/th|.//dt', $row)?->item(0);
                $valueNode = $dom->query($this->settings['value'] ?? './/td|.//dd', $row)?->item(0);

                if (!$keyNode || !$valueNode) continue;

                $key = trim(preg_replace('/\s+/', ' ', $keyNode->textContent));
                $value = trim(preg_replace('/\s+/', ' ', $valueNode->textContent));

                if ($key && $value) $specs[$key] = $value;
            }
        }

        return $specs;
    }
}

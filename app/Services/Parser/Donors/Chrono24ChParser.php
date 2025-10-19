<?php

namespace App\Services\Parser\Donors;

use App\Services\Parser\BaseParser;
use App\Services\Parser\DomParser;

class Chrono24ChParser extends BaseParser
{
    protected string $baseUrl = 'https://www.chrono24.ch/';

    public function pages(): array
    {
        $allPages = [];
        $allProducts = [];

        foreach ($this->donor->pages ?? [] as $page) {
            $html = $this->fetcher->fetch($page['path']);

            if (!$html) continue;

            // 🧩 Извлекаем товары с первой страницы
            $products = $this->extractProductsFromHtml($html, $page['category_id']);
            $allProducts = array_merge($allProducts, $products);

            // 🔍 Находим ссылки пагинации
            $dom = new DomParser($html);
            // 🧭 2. Извлекаем все страницы из пагинации Chrono24
            $pageLinks = $dom->query('//nav[contains(@class,"pagination")]//a');

            $maxPage = 1;
            foreach ($pageLinks as $a) {
                $href = $a->getAttribute('href');
                if (preg_match('/showpage=(\d+)/', $href, $m)) {
                    $num = (int)$m[1];
                    if ($num > $maxPage) {
                        $maxPage = $num;
                    }
                }
            }

            if ($maxPage > 1) {
                for ($i = 2; $i <= $maxPage; $i++) {
                    // заменяем параметр showpage в исходном URL или добавляем, если его нет
                    $base = preg_replace('/(&|\?)showpage=\d+/', '', $page['path']);
                    $separator = str_contains($base, '?') ? '&' : '?';
                    $url = "{$base}{$separator}showpage={$i}";

                    $allPages[] = [
                        'path' => $url,
                        'category_id' => $page['category_id'],
                    ];
                }
            }
        }

        return [
            'products' => $allProducts,
            'pages' => $allPages,
        ];
    }

    /**
     * Парсит товары со страницы пагинации
     */
    public function products(array $page): array
    {
        $html = $this->fetcher->fetch($page['path']);
        if (!$html) return [];

        return $this->extractProductsFromHtml($html, $page['category_id']);
    }

    /**
     * Извлечение списка товаров из <script type="application/ld+json">
     */
    private function extractProductsFromHtml(string $html, int $category_id): array
    {
        $dom = new DomParser($html);
        $scriptNodes = $dom->query('//script[@type="application/ld+json"]');
        $products = [];

        foreach ($scriptNodes as $script) {
            $json = trim($script->nodeValue ?? '');
            if (!$json) continue;

            $data = json_decode($json, true);
            if (!is_array($data)) continue;

            // иногда структура в "@graph"
            $graph = $data['@graph'] ?? null;
            if (!$graph || !is_array($graph)) continue;

            foreach ($graph as $node) {
                if (($node['@type'] ?? '') === 'AggregateOffer' && !empty($node['offers'])) {
                    foreach ($node['offers'] as $offer) {
                        $products[] = [
                            'code' => $this->extractChrono24Id($offer['url'] ?? ''), // Chrono24 не дает артикул → генерируем
                            'category_id' => $category_id,
                            'price' => $offer['price'] ?? null,
                            'currency' => 'CHF',
                            'url' => $offer['url'] ?? null,
                            'status' => (str_contains($offer['availability'] ?? '', 'InStock')) ? 1 : 0,
                        ];
                    }
                }
            }
        }

        return $products;
    }

    private function extractChrono24Id(?string $url): ?string
    {
        if (!$url) return null;

        if (preg_match('/--id(\d+)\.htm/i', $url, $m)) {
            return $m[1];
        }

        return null;
    }

    public function product(): array
    {
        $url = $this->product->url;
        $html = $this->fetcher->fetch($url);

        if (!$html) {
            return [
                'errors' => ['Не удалось загрузить страницу'],
            ];
        }

        $dom = new DomParser($html);

        // 1. Находим скрипт с window.metaData
        $scriptNode = $dom->query('//script[contains(text(), "window.metaData")]')?->item(0);
        if (!$scriptNode) {
            return [
                'errors' => ['Не удалось найти скрипт window.metaData'],
            ];
        }

        $scriptContent = $scriptNode->textContent;

        // 2. Извлекаем JSON из window.metaData
        if (!preg_match('/window\.metaData\s*=\s*(\{.*\});/s', $scriptContent, $matches)) {
            return [
                'errors' => ['Не удалось извлечь JSON из window.metaData'],
            ];
        }

        $metaData = json_decode($matches[1], true);
        if (!$metaData || !isset($metaData['data']['watchNotes'])) {
            return [
                'errors' => ['watchNotes не найдено в metaData'],
            ];
        }

        $watchId = $metaData['data']['watchId'] ?? $metaData['data']['dpWatchId'];

        $name = $metaData['data']["similarProduct-{$watchId}"]['productName'] ?? null;

        if (!$name && $h1 = $dom->query('//h1')?->item(0)?->textContent) {

            // Разбиваем по переносам строк и убираем пустые элементы
            $lines = array_filter(array_map('trim', preg_split('/\r?\n/', $h1)));

            // Берём только первую строку
            $array = array_values($lines);
            $name = array_shift($array) ?? null;
        }

        $watchNotes = $metaData['data']['watchNotes'];

        $description = trim(str_replace($name . ' ·', '', $watchNotes));

        // 1. Находим JSON-LD скрипт
        $jsonScript = $dom->query('//script[@type="application/ld+json"]')?->item(0)?->textContent;

        if (!$jsonScript) {
            return [
                'errors' => ['Не удалось найти JSON-LD данные о товаре'],
            ];
        }

        $jsonData = json_decode($jsonScript, true);
        if (!isset($jsonData['@graph']) || !is_array($jsonData['@graph'])) {
            return [
                'errors' => ['Структура JSON-LD не соответствует ожиданиям'],
            ];
        }

        // 2. Ищем объект типа Product
        $productData = null;
        foreach ($jsonData['@graph'] as $item) {
            if (($item['@type'] ?? '') === 'Product') {
                $productData = $item;
                break;
            }
        }

        if (!$productData) {
            return [
                'errors' => ['Не удалось найти объект Product в JSON-LD'],
            ];
        }

        // 3. Извлекаем изображения
        $images = [];
        if (!empty($productData['image']) && is_array($productData['image'])) {
            foreach ($productData['image'] as $img) {
                $img = $img['contentUrl'] ?? null;

                $images[] = $img ? str_replace('ExtraLarge', 'Zoom', $img) : null;
            }
            $images = array_filter($images); // удаляем null
        }

        // 4. Детали продукта
        $detail = [
            'name' => $name,
            'description' => $description,
            'attributes' => $this->parseAttributesTable($dom),
        ];

        return [
            'detail' => array_filter($detail),
            'images' => $images,
        ];
    }

    protected function parseAttributesTable(DomParser $dom): array
    {
        $rows = $dom->query('//table//tr');
        $result = [];

        foreach ($rows as $row) {
            $keyNode = $dom->query('./td[1]/strong', $row)->item(0);
            $valueNode = $dom->query('./td[2]', $row)->item(0);

            if (!$keyNode || !$valueNode) {
                continue;
            }

            $key = trim($keyNode->textContent);

            // Обработка кнопок типа js-conditions или js-tooltip
            $buttonNode = $dom->query('.//button', $valueNode)->item(0);

            $text = null;
            $description = null;
            $hint = null;

            if ($buttonNode) {
                $text = trim($buttonNode->textContent);

                $pNode = $dom->query('.//p', $valueNode)->item(0);
                $description = $pNode ? trim($pNode->textContent) : null;

                $hint = $buttonNode->hasAttribute('data-content') ? $buttonNode->getAttribute('data-content') : null;
            } else {
                // Удаляем все ссылки <a>
                foreach ($valueNode->getElementsByTagName('a') as $a) {
                    $a->parentNode->removeChild($a);
                }
                $text = trim(preg_replace('/\s+/', ' ', $valueNode->textContent));
            }

            // Формируем значение в зависимости от наличия description или hint
            if ($description || $hint) {
                $value = [];
                if ($text) $value['text'] = $text;
                if ($description) $value['description'] = $description;
                if ($hint) $value['hint'] = $hint;
                $result[$key] = $value;
            } elseif ($text !== '') {
                $result[$key] = $text;
            }
        }

        return $result;
    }


}

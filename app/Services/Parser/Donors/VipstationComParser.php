<?php

namespace App\Services\Parser\Donors;

use App\Services\Parser\BaseParser;
use App\Services\Parser\DomParser;

class VipstationComParser extends BaseParser
{
    protected string $baseUrl = 'https://www.vipstation.com.hk/';

    public function pages(): array
    {
        $allPages = [];
        $allProducts = [];

        foreach ($this->donor->pages ?? [] as $page) {
            $html = $this->fetcher->fetch($page['path']);

            if (!$html) continue;

            $dom = new DomParser($html);

            // 🧩 1. Парсим товары с первой страницы
            $products = $this->extractProductsFromHtml($html, $page['category_id']);
            $allProducts = array_merge($allProducts, $products);

            // 🧭 2. Ищем JS с информацией о страницах
            $scriptNodes = $dom->query('//script[contains(@type, "text/javascript")]');
            foreach ($scriptNodes as $script) {
                $content = $script->nodeValue;

                if (str_contains($content, 'var pageInfo')) {
                    if (preg_match('/var\s+pageInfo\s*=\s*(\{.*?\});/s', $content, $matches)) {
                        $json = preg_replace('/(\w+):/', '"$1":', $matches[1]);
                        $json = str_replace("'", '"', $json);
                        $pageInfo = json_decode($json, true);

                        if (json_last_error() === JSON_ERROR_NONE && !empty($pageInfo['totpage'])) {
                            $param = $pageInfo['parameterName'] ?? '?page=';
                            $total = (int) $pageInfo['totpage'];

                            // убираем query string
                            $baseUrl = preg_replace('/(\?.*)$/', '', $page['path']);
                            $baseUrl = rtrim($baseUrl, '/');

                            for ($i = 2; $i <= $total; $i++) {
                                $nextPage = "{$baseUrl}{$param}{$i}";
                                $allPages[] = [
                                    'path' => $nextPage,
                                    'category_id' => $page['category_id'],
                                ];
                            }
                        }
                    }
                }
            }
        }

        return [
            'products' => $allProducts,
            'pages' => $allPages,
        ];
    }

    public function products(array $page): array
    {
        $html = $this->fetcher->fetch($page['path']);
        if (!$html) {
            return [];
        }

        return $this->extractProductsFromHtml($html, $page['category_id']);
    }

    private function extractProductsFromHtml(string $html, int $category_id): array
    {
        $dom = new DomParser($html);
        $scriptNodes = $dom->query('//script[contains(@type, "text/javascript")]');
        $products = [];

        foreach ($scriptNodes as $script) {
            $content = $script->nodeValue;

            if (str_contains($content, 'var itemList')) {
                if (preg_match('/var\s+itemList\s*=\s*(\[.+?\]);/s', $content, $m)) {
                    $json = trim($m[1]);
                    $items = json_decode($json, true);

                    if (is_array($items)) {
                        $items = array_filter($items, fn($i) => !empty($i['ST_WEB_NAME']));

                        foreach ($items as $item) {
                            $products[] = [
                                'code' => $item['ST_CODE'] ?? null,
                                'category_id' => $category_id,
                                'price' => $item['NO_PRICE'] ?? null,
                                'currency' => $item['ST_CURR'] ?: 'HKD',
                                'url' => "{$this->baseUrl}en/item/{$item['ST_WEB_NAME']}.html",
                                'status' => $item['NO_ISSTOCK'],
                            ];
                        }
                    }
                }
            }
        }

        return $products;
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
        $scriptContent = $dom->query('//script[contains(text(), "var iteminfo")]')?->item(0)?->textContent;

        if (!$scriptContent) {
            return [
                'errors' => ['Не удалось найти данные о товаре'],
            ];
        }

        $itemInfo = [];
        $images = [];

        if (preg_match('/var\s+iteminfo\s*=\s*(\{.*?\});/s', $scriptContent, $infoMatch)) {
            $itemInfo = json_decode($infoMatch[1] ?? '{}', true) ?: [];
        }

        if (preg_match('/var\s+imgList\s*=\s*(\[[^\]]*\]);/s', $scriptContent, $imgMatch)) {
            $images = json_decode($imgMatch[1] ?? '[]', true) ?: [];
        }

        return [
            'detail' => array_filter([
                'name' => $itemInfo['ST_NAME'] ?? null,
                'category' => implode('/', array_filter([
                    $itemInfo['ST_WEB_CATALOG'] ?? null,
                    $itemInfo['ST_WEB_SUBCATALOG'] ?? null
                ])),
                'description' => $this->cleanHtmlDescription($itemInfo['ST_ADVERTISING'] ?? ''),
                'sku' => $itemInfo['ST_CODE'] ?? null,
                'attributes' => !empty($itemInfo['ST_PRODETAILS'])
                    ? array_column($itemInfo['ST_PRODETAILS'], 'ST_VALUE', 'ST_KEY')
                    : [],
            ]),
            'images' => $images ?? [],
        ];
    }

    /**
     * Очищает описание товара от лишнего HTML и рекламного мусора.
     */
    protected function cleanHtmlDescription(string $html): string
    {
        // 1️⃣ Удаляем внешние <span> в начале и конце
        $html = preg_replace('/^<span[^>]*>/i', '', $html);
        $html = preg_replace('/<\/span>$/i', '', $html);

        // 2️⃣ Удаляем пустые теги <h2> и <span> внутри них
        $html = preg_replace('/<h2>\s*<span[^>]*>\s*<\/span>\s*<\/h2>/i', '', $html);

        // 3️⃣ Удаляем специальные символы, хештеги и лишние <br> в конце
        $html = preg_replace('/(?:<br\s*\/?>\s*)*(?:※<br\s*\/?>.*|#.*)<$/s', '', $html);

        // 4️⃣ Убираем лишние <br> в начале и конце
        $html = preg_replace('/^(<br\s*\/?>\s*)+/i', '', $html);
        $html = preg_replace('/(\s*<br\s*\/?>)+$/i', '', $html);

        // 5️⃣ Тримим пробелы по бокам
        return trim($html);
    }
}

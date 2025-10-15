<?php

namespace App\Services\Parser\Donors;

use App\Services\Parser\BaseParser;
use App\Services\Parser\DomParser;
use DOMElement;

class WatchnianComParser extends BaseParser
{
    protected string $baseUrl = 'https://watchnian.com/';

    public function pages(): array
    {
        $allProducts = [];
        $allPages = [];

        foreach ($this->donor->pages ?? [] as $page) {
            $html = $this->fetcher->fetch($page['path']);
            if (!$html) continue;

            // Парсим товары с текущей страницы
            $allProducts = array_merge($allProducts, $this->extractProductsFromHtml($html, $page['category_id']));

            $dom = new DomParser($html);
            $paginationLists = $dom->query('//ul[contains(@class,"pagination")]');

            $maxPage = 1;
            $templateUrl = null;

            foreach ($paginationLists as $ul) {
                // Ищем ссылку на последнюю страницу
                $lastLinkNodes = $dom->query('.//li[contains(@class,"pager-last")]/a', $ul);
                $lastLink = $lastLinkNodes?->item(0);

                if ($lastLink) {
                    $lastHref = $lastLink->getAttribute('href');
                    if (preg_match('/_p(\d+)/', $lastHref, $matches)) {
                        $maxPage = (int) $matches[1];
                        $templateUrl = preg_replace('/_p\d+/', '_p{page}', $lastHref);
                    }
                    break;
                }
            }

            // Генерируем ссылки на все страницы
            if ($templateUrl && $maxPage > 1) {
                for ($i = 2; $i <= $maxPage; $i++) {
                    $allPages[] = [
                        'path' => $this->makeAbsoluteUrl(str_replace('{page}', $i, $templateUrl)),
                        'category_id' => $page['category_id']
                    ];
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
        if (!$html) return [];

        return $this->extractProductsFromHtml($html, $page['category_id']);
    }

    /**
     * Вытаскиваем товары из HTML
     */
    private function extractProductsFromHtml(string $html, int $category_id): array
    {
        $dom = new DomParser($html);
        $products = [];

        $items = $dom->query('//ul[contains(@class,"block-thumbnail-t")]/li/dl[contains(@class,"block-thumbnail-t--goods")]');

        foreach ($items as $item) {
            $a = $dom->query('.//a', $item)?->item(0);
            if (!$a) continue;

            $url = $this->makeAbsoluteUrl($a->getAttribute('href'));

            $code = $this->extractProductCode($url);

            $priceNode = $dom->query('.//div[contains(@class,"block-thumbnail-t--price")]/span[contains(@class,"num")]', $item)?->item(0);
            $price = $priceNode ? (float) str_replace(',', '', $priceNode->textContent) : null;

            $products[] = [
                'code' => $code,
                'category_id' => $category_id,
                'url' => $url,
                'price' => $price,
                'currency' => 'JPY',
            ];
        }

        return $products;
    }

    /**
     * Парсинг конкретного товара
     */
    public function product(): array
    {
        $url = $this->product->url;
        $html = $this->fetcher->fetch($url);

        if (!$html) {
            return ['errors' => ['Не удалось загрузить страницу']];
        }

        $dom = new DomParser($html);

        $h1 = $dom->query('//h1')?->item(0)?->textContent ?? '';

        $pattern = '/【([^】]+)】$/u'; // последние квадратные скобки 【…】
        preg_match($pattern, $h1, $matches);

        $category = $matches[1] ?? null; // категория товара
        $name = preg_replace($pattern, '', $h1); // убираем последнюю категорию из строки

        $name = trim($name);

        // Основные товары
        $specItems = $dom->query('//dl[contains(@class,"goods-spec")]/div[contains(@class,"goods-spec-item")]');

        $attributes = [];

        foreach ($specItems as $item) {
            /** @var DOMElement $item */
            $dtNode = $dom->query('.//dt', $item)->item(0);
            $ddNode = $dom->query('.//dd', $item)->item(0);

            if (!$dtNode || !$ddNode) {
                continue;
            }

            $key = trim($dtNode->textContent);

            // Проверяем наличие вложенных <dl>
            $innerDlNodes = $dom->query('.//dl', $ddNode);
            if ($innerDlNodes->length > 0) {
                $parts = [];
                foreach ($innerDlNodes as $innerDl) {
                    $innerItems = $dom->query('.//div[contains(@class,"goods-spec-disc-list-item")]', $innerDl);
                    foreach ($innerItems as $innerItem) {
                        $innerKeyNode = $dom->query('.//dt', $innerItem)->item(0);
                        $innerDdNode = $dom->query('.//dd', $innerItem)->item(0);
                        if ($innerKeyNode && $innerDdNode) {
                            $innerKey = trim($innerKeyNode->textContent);
                            $innerValue = trim(preg_replace('/\s+/u', ' ', $innerDdNode->textContent));
                            $innerValue = implode('｜', array_map('trim', preg_split('/\r?\n/', $innerValue)));
                            $parts[] = "{$innerKey}: {$innerValue}";
                        }
                    }
                }
                $attributes[$key] = implode('｜', $parts);
            } else {
                // Простое поле с возможными несколькими строками
                $value = trim(preg_replace('/\s+/u', ' ', $ddNode->textContent));
                $value = implode('｜', array_map('trim', preg_split('/\r?\n/', $value)));
                $attributes[$key] = $value;
            }
        }


        $images = [];
        $sliderNodes = $dom->query('//div[contains(@class,"block-detail-image-slider")]//figure[contains(@class,"block-detail-image-slider--item")]');

        foreach ($sliderNodes as $figure) {
            /** @var DOMElement $figure */
            $aNode = $dom->query('.//a', $figure)->item(0);
            $imgNode = $dom->query('.//img', $figure)->item(0);

            $url = null;
            if ($aNode && $aNode->hasAttribute('href')) {
                $url = $aNode->getAttribute('href');
            } elseif ($imgNode && $imgNode->hasAttribute('data-src')) {
                $url = $imgNode->getAttribute('data-src');
            }

            if ($url) {
                // делаем абсолютным
                $url = $this->makeAbsoluteUrl($url);
                $images[] = $url;
            }
        }

        return [
            'detail' => [
                'name' => $name,
                'category' => $category,
                'attributes' => $attributes,
            ],
            'images' => $images,
        ];
    }




    /**
     * Преобразует относительный путь в абсолютный URL.
     *
     * @param string|null $path
     * @return string|null
     */
    protected function makeAbsoluteUrl(?string $path): ?string
    {
        if (!$path) {
            return null;
        }

        if (str_starts_with($path, 'http')) {
            return $path;
        }

        return rtrim($this->baseUrl, '/') . '/' . ltrim($path, '/');
    }

    protected function extractProductCode(string $href): ?string
    {
        if (preg_match('#/shop/g/gik-00-(\d+)/#', $href, $matches)) {
            return ltrim($matches[1], '0');
        }

        return md5($href);
    }
}

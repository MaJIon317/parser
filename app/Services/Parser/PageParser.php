<?php

namespace App\Services\Parser;

use App\Exceptions\ParserException;
use App\Models\Donor;
use App\Models\Product;
use Exception;
use Illuminate\Support\Str;

class PageParser
{
    public HtmlFetcher $fetcher;
    protected UrlHelper $urlHelper;
    protected array $processedUrls = [];
    protected int $batchSize = 50;

    public function __construct(
        protected Donor $donor
    ) {
        $this->fetcher = new HtmlFetcher($donor);
        $this->urlHelper = new UrlHelper($donor);
    }

    /**
     * @throws ParserException
     */
    public function parse(bool $test = false): array
    {
        $pagesToParse = $this->donor->setting['pages'] ?? [];
        $productsBatch = [];

        while ($pagesToParse) {
            $pageUrl = array_shift($pagesToParse);

            if (in_array($pageUrl, $this->processedUrls)) continue;
            $this->processedUrls[] = $pageUrl;

            $html = $this->fetcher->fetch($pageUrl);

            if (!$html) continue;

            $dom = new DomParser($html);

            // --- Парсим товары ---
            $pageProducts = $this->parseProducts($dom);
            foreach ($pageProducts as $product) {
                $url = $product['url'] ?? null;
                if (!$url || in_array($url, $this->processedUrls)) continue;

                $this->processedUrls[] = $url;
                $productsBatch[] = $product;

                if (count($productsBatch) >= $this->batchSize) {
                    $this->saveBatchProducts($productsBatch, $test);

                    $productsBatch = [];
                }
            }

            // --- Пагинация ---
            $paginations = $this->extractPagination($dom);

            foreach ($paginations as $paginationUrl) {
                if (!in_array($paginationUrl, $this->processedUrls) && !in_array($paginationUrl, $pagesToParse)) {
                    $pagesToParse[] = $paginationUrl;
                }

            }

            if ($test) {
                dd(['pagination' => $pagesToParse]);
            }

            unset($dom, $xpath);
            gc_collect_cycles();
        }

        if (!empty($productsBatch)) {
            $this->saveBatchProducts($productsBatch, $test);
        }

        return [
            'productsBatch' => count($productsBatch),
        ];
    }

    /**
     * @throws ParserException
     */
    public function parseProducts(DomParser $dom): array
    {
        $settings = $this->donor->setting;
        $products = [];

        $productNodes = $dom->query($settings['products']['container'] ?? null);

        if (!$productNodes) return [];

        $hasUrlKeywords = $settings['product']['has_url'] ?? [];

        foreach ($productNodes as $node) {
            $urlNode = $dom->query($settings['product']['url']['container'] ?? './/a', $node)?->item(0);

            $priceNode = $dom->query($settings['product']['price']['container'] ?? './/span', $node)?->item(0);
            $priceNode = $dom->replace($priceNode?->textContent, $settings['product']['price']['replace'] ?? null);

            $url = $this->urlHelper->normalize($urlNode?->getAttribute('href') ?? '');

            if (!$url) {
                continue;
            }

            if ($hasUrlKeywords) {
                $valid = false;
                foreach ($hasUrlKeywords as $keyword) {
                    if (stripos($url, $keyword) !== false) {
                        $valid = true;
                        break;
                    }
                }
                if (!$valid) continue;
            }

            $products[] = [
                'code' => md5($url),
                'url' => $url,
                'price' => $priceNode,
                'status' => $priceNode ? 'active' : 'inactive',
            ];
        }

        return $products;
    }

    protected function extractPagination(DomParser $dom): array
    {
        $settings = $this->donor->setting;
        $paginationSelector = $settings['pagination']['container'] ?? null;
        if (!$paginationSelector) return [];

        $hasUrlKeywords = $settings['pagination']['has_url'] ?? [];

        $links = [];

        $containers = $dom->query($paginationSelector);
        if (!$containers) return [];

        foreach ($containers as $container) {
            $aTags = $dom->query('.//a[@href]', $container);
            foreach ($aTags as $a) {
                $href = trim($a->getAttribute('href'));
                if (!$href) continue;

                if ($hasUrlKeywords) {
                    foreach ($hasUrlKeywords as $keyword) {
                        if (stripos($href, $keyword) !== false) {
                            $links[] = $this->urlHelper->normalize($href);
                            break;
                        }
                    }
                } else {
                    $links[] = $this->urlHelper->normalize($href);
                }
            }
        }

        $links = array_values(array_unique(array_filter($links)));
        if (empty($links)) return [];

        // --- Дополняем пропущенные страницы ---
        $pageNumbers = [];
        foreach ($links as $link) {
            if (preg_match('/(\d+)/', $link, $m)) {
                $pageNumbers[] = (int)$m[1];
            }
        }

        if (!$pageNumbers) return $links;

        sort($pageNumbers);
        $min = min($pageNumbers);
        $max = max($pageNumbers);
        $baseLink = $links[0];
        $basePattern = null;

        if ($hasUrlKeywords) {
            foreach ($hasUrlKeywords as $keyword) {
                if (stripos($baseLink, $keyword) !== false) {
                    $basePattern = preg_replace('/(\d+)/', '{num}', $baseLink);
                    break;
                }
            }
        } else {
            $basePattern = preg_replace('/(\d+)/', '{num}', $baseLink);
        }

        if (!$basePattern) return $links;

        $fullList = [];
        for ($i = $min; $i <= $max; $i++) {
            $fullList[] = str_replace('{num}', $i, $basePattern);
        }

        return array_values(array_unique($fullList));
    }


    // Сохраняем в БД
    protected function saveBatchProducts(array $products, bool $test = false): void
    {
        if ($test) {
            dump(['products' => $products]);
            return;
        }

        foreach ($products as &$product) {
            $product['uuid'] = $product['uuid'] ?? (string) Str::uuid();
            $product['donor_id'] = $product['donor_id'] ?? $this->donor->id;
        }
        unset($product);

        foreach ($products as $data) {
            Product::updateOrCreate(
                [
                    'donor_id' => $data['donor_id'],
                    'code' => $data['code']
                ],
                $data
            );
        }
    }
}

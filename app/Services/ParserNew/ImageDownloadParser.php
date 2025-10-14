<?php

namespace App\Services\ParserNew;

use App\Models\Donor;
use App\Models\Product;
use App\Services\Parser\DomParser;
use App\Services\Parser\HtmlFetcher;
use App\Services\Parser\UrlHelper;
use Illuminate\Support\Facades\Storage;

/*
 * Парсинг и скачивание изображений
 * Скачивает только уникальные изображения.
 */
class ImageDownloadParser
{
    public HtmlFetcher $fetcher;
    protected UrlHelper $urlHelper;

    public function __construct(
        protected Product $product
    )
    {
        $donor = $product->donor;

        $this->fetcher = new HtmlFetcher($donor);
        $this->urlHelper = new UrlHelper($donor);
    }

    public function parse(DomParser $dom, string $query): array
    {
        $urls = [];
        $nodes = $dom->query($query);

        if (!$nodes) return [];

        foreach ($nodes as $node) {
            $src = $this->urlHelper->normalize($node->getAttribute('data-src')
                    ?: $node->getAttribute('src')
                    ?: $node->getAttribute('href'));

            if (!$src) continue;

            $urls[] = $this->download($src);
        }

        return array_values(array_unique(array_filter($urls)));
    }

    protected function download(string $url): ?string
    {
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = substr(md5($url), 0, 12) . '.' . $ext;
        $path = "{$this->product->uuid}/{$filename}";

        if (Storage::disk('public')->exists($path)) return Storage::url($path);

        $content = $this->fetcher->fetch($url);

        if (!$content) return null;

        Storage::disk('public')->put($path, $content);
        return Storage::url($path);
    }
}

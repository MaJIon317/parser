<?php

namespace App\Services\Parser\Donors;

use App\Services\Parser\BaseParser;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Http;

class WatchnianParser extends BaseParser
{
    protected string $donor = 'watchnian.com';

    public function parseProduct(string $url): ?array
    {
        $response = Http::withOptions([
            'proxy' => $this->proxy,
            'timeout' => 20,
        ])->get($url);

        if ($response->failed()) {
            return null;
        }

        $html = $response->body();
        $crawler = new Crawler($html);

        return [
            'code' => md5($url),
            'url' => $url,
            'name' => $crawler->filter('h1.product-title')->text(),
            'data' => [
                'price' => $crawler->filter('.price')->text(),
                'description' => $crawler->filter('.description')->text(),
            ],
            'images' => $crawler->filter('.product-image img')->each(fn($img) => $img->attr('src')),
        ];
    }

    public function parseList(string $categoryUrl): iterable
    {
        // Возвращает список ссылок на товары
    }
}

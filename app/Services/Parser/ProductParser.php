<?php

namespace App\Services\Parser;

use App\Exceptions\ParserException;
use App\Models\Product;

class ProductParser
{
    public HtmlFetcher $fetcher;
    protected UrlHelper $urlHelper;

    public function __construct(
        protected Product $product
    ) {
        $donor = $this->product->donor;

        $this->fetcher = new HtmlFetcher($donor);
        $this->urlHelper = new UrlHelper($donor);
    }

    /**
     * @throws ParserException
     */
    public function parse(bool $test = false): Product
    {
        $settings = $this->product->donor->setting;

        // Получаем HTML через HtmlFetcher
        $html = $this->fetcher->fetch($this->product->url);

        // Если страница не найдена
        if (!$html && !$test) {
            $this->product->update([
                'parsing_status' => 'not_found',
                'status' => 'delete',
                'errors' => [
                    'Error while parsing product',
                ]
            ]);

            return $this->product;
        }

        $data = [];

        $dom = new DomParser($html);

        // Парсим характеристики
        $data['attributes'] = (new ProductSpecParser($settings['product_page']['attributes'] ?? []))->parse($dom);

        // Парсим изображения
        $images = (new ProductImageParser($this->product))->parse($dom, $settings['product_page']['images']['container'] ?? '');

        // Получаем название товара
        $name = $dom->text(
                $settings['product_page']['name']['container'] ?? null,
                $settings['product_page']['name']['regular'] ?? null,
                );

        // ✅ Категория (по кастомному XPath)
        $data['category'] = $dom->text(
                            $settings['product_page']['category']['container'] ?? null,
                            $settings['product_page']['category']['regular'] ?? null
                            );

        // Обновляем продукт в базе
        $update = [
            'name' => $name,
            'data' => $data,
            'images' => $images,
            'last_parsing' => now(),
            'parsing_status' => 'wait',
            'errors' => null,
        ];

        if ($test) {
            dd($update);
        } else {
            $this->product->update($update);

            // Сразу выполним перевод, если требуется
            $this->product->getTranslationAttribute();
        }

        return $this->product;
    }
}

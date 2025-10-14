<?php

namespace App\Services\Parser;

use App\Exceptions\ParserException;
use App\Models\Product;
use App\Models\Donor;
use Exception;

class ParserService
{
    public function __construct(
        protected Donor $donor
    ) {}

    public function parsePages(): array
    {
        try {
            return (new PageParser($this->donor))->parse();
        } catch (ParserException $e) {
            return $e->getDetailedMessage();
        }
    }

    // Для теста всегда парсим только одну страинцу
    public function parsePage(string $page): array
    {
        try {
            $html = (new HtmlFetcher($this->donor))->fetch($page);
            $dom = new DomParser($html);

            return (new PageParser($this->donor))->parseProducts($dom);
        } catch (ParserException $e) {
            return $e->getDetailedMessage();
        }
    }

    /*
     * Парсим товар
     */
    public function parseProductPage(Product $product): Product
    {
        try {
            return (new ProductParser($product))->parse();
        } catch (ParserException $e) {
            $product->update([
                'errors' => $e->getDetailedMessage(),
            ]);

            return $product;
        }
    }

}

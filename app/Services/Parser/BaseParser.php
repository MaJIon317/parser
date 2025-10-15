<?php

namespace App\Services\Parser;

use App\Facades\Currency;
use App\Jobs\ParseDonorJob;
use App\Models\Donor;
use App\Models\Product;
use Illuminate\Support\Collection;
use App\Services\Parser\Concerns\LogsParser;

/**
 * Базовый парсер для всех доноров.
 * Реализует стандартную логику очередей, валидации и сохранения.
 */
abstract class BaseParser
{
    use LogsParser;

    protected Collection $currencies;

    protected ProductSaver $productSaver;

    public function __construct(
        protected HtmlFetcher $fetcher,
        protected ?Donor $donor = null,
        protected ?Product $product = null,
    ) {
        $this->currencies = Currency::getAvailableCurrencies();
        $this->productSaver = new ProductSaver();
    }

    /**
     * Парсит первую страницу каталога и возвращает товары + ссылки пагинации.
     *
     * @return array{
     *     products: string[],
     *     pages: string[]
     * }
     */
    abstract public function pages(): array;

    /**
     * Должен вернуть данные о товарах на странице категории.
     * @param $page array{
     *     path: string,
     *     category_id: string,
     * }
     * @return array<int, array{
     *     code: string|null,
     *     url: string|null,
     *     price?: float|null,
     *     currency: string
     * }>
     */
    abstract public function products(array $page): array;

    /**
     * Основной метод парсинга, который должен вернуть данные о товаре.
     *
     * @return array{
     *     detail: array{
     *         name: string|null,
     *         category: string|null,
     *         description: string|null,
     *         sku: string|null,
     *         attributes: array<string, string>
     *     },
     *     images: string[],
     *     errors?: string[]
     * }
     */
    abstract public function product(): array;

    // Только на первой странице каталога вызываем
    public function parsePages(): void
    {
        $this->startLog('parser_donor', [
            'donor_id' => $this->donor->id,
        ]);

        try {
            $result = $this->pages();
            $products = $result['products'] ?? [];
            $pages = $result['pages'] ?? [];

            $this->parseProducts($products);

            foreach ($pages as $page) {
                ParseDonorJob::dispatch($this->donor, $page)
                    ->onQueue('high');
            }

            $this->finishLog('success', 'The parsing of the catalog pages has been completed', [
                'pages' => count($pages),
                'products' => count($products),
            ]);
        } catch (\Throwable $e) {
            $this->failLog($e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }

    // Сохраняем постранично
    public function parseProducts($products): array
    {
        return $this->productSaver->saveList($this->donor, $products);
    }

    // Сохраняем товар
    public function parseProduct(): array
    {
        $this->startLog('parser_product', [
            'donor_id' => $this->donor->id,
        ]);

        try {
            $data = $this->product();

            $product = $this->productSaver->save($this->product, $data);

            $this->finishLog('success', 'Product parsing completed', [
                'detail' => $product['product']['detail'],
                'images' => $product['product']['images'],
            ]);

            return $product;
        } catch (\Throwable $e) {
            $this->failLog($e->getMessage(), [
                'trace' => $e->getTraceAsString(),
            ]);
        }

        return [];
    }
}

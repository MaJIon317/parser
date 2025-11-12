<?php

namespace App\Jobs;

use App\Models\Donor;
use App\Services\Parser;
use App\Services\Parser\Concerns\LogsParser;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Парсим страницы каталога на наличие товаров.
 *
 * Если передан $url — парсим конкретную страницу.
 * Если $url = null — запускаем полный цикл (parsePages + постановка задач).
 */
class ParseDonorJob implements ShouldQueue
{
    use Queueable, LogsParser;

    /**
     * Уникальность job — не дублируем в течение часа.
     */
    public int $uniqueFor = 3600;

    public function __construct(
        public Donor $donor,
        public ?array $page = null
    ) {}

    /**
     * Выполнение задачи.
     * @throws Exception
     * @throws Throwable
     */
    public function handle(): void
    {
        $this->startLog($this->page ? 'parser_page' : 'parser_donor', [
            'donor_id' => $this->donor->id,
        ]);

        try {
            $parser = Parser::make($this->donor);

            if ($this->page) {
                // Парсим конкретную страницу пагинации
                $products = $parser->products($this->page);

                $parser->parseProducts($products);

                $this->finishLog(!$products ? 'error' : 'success', 'Catalog page parsing completed', [
                    'page' => $this->page,
                    'products' => count($products),
                ]);
            } else {
                // Парсим первую страницу и создаём задачи на остальные страницы
                $result = $parser->parsePages();

                $this->finishLog(!empty($result['skipped']) ? 'error' : 'success', 'The parsing of the catalog pages has been completed', [
                    'pages' => count($result['pages']),
                    'products' => count($result['products']),
                    ...$result
                ]);
            }
        } catch (Throwable $e) {
            $this->failLog($e->getMessage(), [
                'trace' => $e,
            ]);

            Log::error("ParseDonorJob failed", [
                'donor_id' => $this->donor->id,
                'page' => $this->page['path'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

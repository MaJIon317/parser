<?php

namespace App\Jobs;

use App\Models\Donor;
use App\Services\Parser;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

/**
 * Парсим страницы каталога на наличие товаров.
 *
 * Если передан $url — парсим конкретную страницу.
 * Если $url = null — запускаем полный цикл (parsePages + постановка задач).
 */
class ParseDonorJob implements ShouldQueue
{
    use Queueable;

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
     */
    public function handle(): void
    {
        try {
            $parser = Parser::make($this->donor);

            if ($this->page) {
                // Парсим конкретную страницу пагинации
                $products = $parser->products($this->page);

                $parser->parseProducts($products);
            } else {
                // Парсим первую страницу и создаём задачи на остальные страницы
                $parser->parsePages();
            }
        } catch (Exception $e) {
            Log::error("ParseDonorJob failed", [
                'donor_id' => $this->donor->id,
                'page' => $this->page['path'] ?? null,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }
}

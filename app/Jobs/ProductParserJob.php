<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Parser\ParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
 * Парсим отдельный товар
 */
class ProductParserJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public Product $product,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new ParserService($this->product->donor))->parseProductPage($this->product);
    }
}

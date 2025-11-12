<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Parser;
use Exception;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
 * Парсим страницу товара
 */
class ParseProductJob implements ShouldQueue
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
     * @throws Exception
     */
    public function handle(): void
    {

        Parser::make($this->product)->parseProduct();

    }
}

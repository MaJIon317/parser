<?php

namespace App\Jobs;

use App\Models\Donor;
use App\Services\Parser\ParserService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
 * Парсим список товаров
 */
class PagesParserJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        protected Donor $donor,
    ) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        (new ParserService($this->donor))->parsePages();
    }
}

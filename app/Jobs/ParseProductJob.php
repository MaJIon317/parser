<?php

namespace App\Jobs;

use App\Models\Product;
use App\Services\Parser\ParserManager;
use App\Services\Parser\ProxyManager;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;

class ParseProductJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        protected string $donor,
        protected string $url
    ) {}

    public function handle(): void
    {
        $proxy = ProxyManager::getRandomActive();
        $parser = ParserManager::make($this->donor);

        if (! $this->shouldParse($this->donor, $this->url)) {
            Log::channel('parser')->info("Skipped {$this->url}");
            return;
        }

        sleep(rand(1, 3)); // небольшая задержка для антиспама

        $data = $parser->parseProduct($this->url);
        if ($data) {
            $parser->saveProduct($data);
            Log::channel('parser')->info("Parsed: {$this->url}");
        }
    }

    protected function shouldParse(string $donor, string $url, int $minutes = 5): bool
    {
        $product = Product::where('donor', $donor)
            ->where('url', $url)
            ->where('last_parsing', '>=', now()->subMinutes($minutes))
            ->first();

        return !$product;
    }
}

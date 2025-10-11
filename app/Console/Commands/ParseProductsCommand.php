<?php

namespace App\Console\Commands;

use App\Services\Parser\ParserManager;
use App\Services\Parser\ProxyManager;
use Illuminate\Console\Command;

class ParseProductsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'parser:run {--donors=} {--mode=full}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function handle(): void
    {
        $donors = collect(explode(',', $this->option('donors')));
        $mode = $this->option('mode');

        foreach ($donors as $donorName) {
            dispatch(function () use ($donorName, $mode) {
                $proxy = ProxyManager::getRandomActive();
                $parser = ParserManager::make($donorName, $proxy);

                if ($mode === 'full') {
                    foreach ($parser->parseList($parser->donor->base_url) as $url) {
                        dispatch(new \App\Jobs\ParseProductJob($parser->donor->name, $url));
                    }
                } else {
                    // mode=single — аналогично
                }

                \Log::channel('parser')->info("🚀 Запущен парсер для {$donorName}");
            })->onQueue('parsers');
        }
    }

}

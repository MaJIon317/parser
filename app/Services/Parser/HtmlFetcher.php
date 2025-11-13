<?php

namespace App\Services\Parser;

use App\Models\Donor;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class HtmlFetcher
{
    protected int $maxProxySwitches = 6;
    protected int $globalTimeout = 120; // сек
    protected ProxyManager $proxyManager;

    public function __construct(protected Donor $donor)
    {
        $this->proxyManager = new ProxyManager();
    }

    /**
     * Fetch a single URL
     *
     * @param string $url
     * @return string|null HTML-код страницы или null
     */
    public function fetch(string $url): ?string
    {
        $proxies = $this->proxyManager->getActiveSorted();
        if (empty($proxies)) {
            Log::error("HtmlFetcher: no active proxies found!");
            return null;
        }

        $cacheKey = 'htmlfetcher:' . md5($url);

        $attempts = 0;
        $pageHtml = null;

        foreach ($proxies as $proxy) {
            $attempts++;
            $proxyStr = $this->proxyManager->proxyToCurlString($proxy);

            try {
                $res = $this->runNodeFetcher($url, $proxyStr, $this->globalTimeout);

                if ($res['exit'] === 0 && !empty($res['body']) && isset($res['body']['html'])) {
                    $pageHtml = $res['body']['html'];

                    // Проверка на антибот
                    if (stripos($pageHtml, 'Verify you are human') !== false ||
                        stripos($pageHtml, 'Just a moment') !== false ||
                        stripos($pageHtml, 'Checking your browser') !== false
                    ) {
                        Log::warning("HtmlFetcher: page still contains anti-bot challenge, skipping cache");
                        $pageHtml = null;
                    }
                }

                if (!$pageHtml) {
                    $this->proxyManager->markFailure($proxy);
                    $this->proxyManager->blacklist($proxy);
                } else {
                    break;
                }

            } catch (\Throwable $e) {
                $this->proxyManager->markFailure($proxy);
                $this->proxyManager->blacklist($proxy);
                Log::warning("HtmlFetcher: exception with proxy {$proxyStr} — " . $e->getMessage());
            }

            if ($attempts >= $this->maxProxySwitches) {
                Log::info("HtmlFetcher: reached maxProxySwitches ({$this->maxProxySwitches}) for {$url}");
                break;
            }
        }

        // Просто сохраняем HTML для дебага, не используем кэш при повторном fetch
        if ($pageHtml) {
            try {
                Cache::driver('file')->put($cacheKey, $pageHtml, now()->addDays(1)); // можно долго, только для дебага
            } catch (\Throwable $e) {
                Log::warning("HtmlFetcher: failed to cache HTML for debug: " . $e->getMessage());
            }
        }

        return $pageHtml;
    }

    /**
     * Run Node.js fetcher (single URL)
     *
     * @param string $url
     * @param string|null $proxyStr
     * @param int|null $timeoutSec
     * @return array
     */
    protected function runNodeFetcher(string $url, ?string $proxyStr = null, ?int $timeoutSec = null): array
    {
        try {
            $response = Http::timeout($timeoutSec ?? 30)->post(config('services.parser.url', 'http://127.0.0.1:3200/fetch'), [
                'url' => $url,
                'proxy' => $proxyStr,
            ]);

            $json = $response->json();
            return [
                'exit' => 0,
                'body' => $json,
            ];

        } catch (\Throwable $e) {
            \Log::error("HtmlFetcher: failed to fetch via Node server: " . $e->getMessage());
            return ['exit' => 1, 'body' => null];
        }
    }
}

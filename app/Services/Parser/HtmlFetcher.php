<?php

namespace App\Services\Parser;

use App\Models\Donor;
use App\Models\Proxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * HtmlFetcher — человекоподобный загрузчик HTML
 */
class HtmlFetcher
{
    protected array $userAgents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 Chrome/120.0.0.0 Safari/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 13_6) AppleWebKit/605.1.15 Safari/605.1.15',
    ];

    protected array $acceptLanguages = [
        'en-US,en;q=0.9',
        'en-GB,en;q=0.9'
    ];
    protected array $referers = [
        'https://www.google.com/',
        'https://www.bing.com/'
    ];

    protected int $cacheMinutes = 3;
    protected int $maxAttemptsPerProxy = 2;
    protected int $maxProxySwitches = 6;
    protected int $globalTimeout = 25;
    protected int $connectTimeout = 8;

    protected string $cookieFile;

    public function __construct(
        protected Donor $donor
    )
    {
        $this->cookieFile = storage_path("app/cookies_donor_{$this->donor->id}.txt");
    }

    /**
     * Fetch HTML
     */
    public function fetch(string $url, bool $forceReload = false): ?string
    {
        $cacheKey = 'htmlfetcher:' . md5($url);

        if (!$forceReload && ($cached = Cache::get($cacheKey))) {
            return $cached;
        }

        $this->enforceRateLimit();
        $this->humanDelayBeforeRequest();

        $proxies = $this->getActiveProxies();
        $triedProxies = 0;
        $attemptedProxies = [];

        if (!$proxies) {
            // Попробуем прямой запрос
            $resp = $this->attemptWithRetries($url, null);

            // ✅ Пропускаем страницу, если 404
            if (($resp['code'] ?? 0) === 404) {
                Log::info("HtmlFetcher: 404 detected, skipping URL {$url}");
                return null;
            }

            if ($this->isGoodResponse($resp['code'] ?? 0, $resp['body'] ?? '')) {
                Cache::put($cacheKey, $resp['body'], now()->addMinutes($this->cacheMinutes));
                return $resp['body'];
            }

            Log::warning("HtmlFetcher: direct request failed for {$url}. Trying proxies...");
        }

        $proxies = $this->sortProxiesBySuccess($proxies);

        foreach ($proxies as $proxy) {
            $proxyKey = $this->proxyCacheKey($proxy);
            if (Cache::has($proxyKey)) continue;

            $attemptedProxies[] = $proxy->id ?? ($proxy->host . ':' . $proxy->port);
            $triedProxies++;

            $resp = $this->attemptWithRetries($url, $proxy);

            // ✅ Пропускаем страницу, если 404
            if (($resp['code'] ?? 0) === 404) {
                Log::info("HtmlFetcher: 404 detected via proxy {$proxy->host}, skipping URL {$url}");
                return null;
            }

            if ($this->isGoodResponse($resp['code'] ?? 0, $resp['body'] ?? '')) {
                $this->increaseProxySuccess($proxy);
                Cache::put($cacheKey, $resp['body'], now()->addMinutes($this->cacheMinutes));
                return $resp['body'];
            } else {
                $this->decreaseProxySuccess($proxy);
                if ($this->isHardFailure($resp['code'] ?? 0, $resp['body'] ?? '')) {
                    Cache::put($proxyKey, true, now()->addMinutes(30)); // blacklisted
                }
            }

            if ($triedProxies >= $this->maxProxySwitches) break;
        }

        // fallback на headless browser (Puppeteer)
        $html = $this->fetchWithHeadlessBrowser($url);
        if ($html) {
            Cache::put($cacheKey, $html, now()->addMinutes($this->cacheMinutes));
            return $html;
        }

        Log::error("HtmlFetcher: all attempts failed for URL {$url}. Proxies tried: " . implode(',', $attemptedProxies));
        return null;
    }

    protected function enforceRateLimit(): void
    {
        $key = "donor_rate_limit:{$this->donor->id}";
        $count = Cache::get($key, 0);

        if ($count >= $this->donor->rate_limit) {

            $defaultTtl = 60;

            $store = Cache::getStore();
            $ttl = method_exists($store, 'getRedis')
                ? $store->getRedis()->ttl($key)
                : $defaultTtl;

            if ($ttl < 0) $ttl = $defaultTtl;

            sleep($ttl);
            Cache::put($key, 0, 60);
        }

        Cache::increment($key);
        Cache::put($key, Cache::get($key, 1), 60);
    }

    protected function attemptWithRetries(string $url, $proxy = null): array
    {
        $attempt = 0;
        $backoffBase = 0.5;
        $lastResp = ['code' => 0, 'body' => null];

        while ($attempt < $this->maxAttemptsPerProxy) {
            $attempt++;
            usleep(rand(200, 800) * 1000);

            try {
                $resp = $this->requestOnce($url, $proxy);
                $lastResp = $resp;

                // ✅ Если 404 — сразу выходим, не повторяем
                if (($resp['code'] ?? 0) === 404) {
                    Log::info("HtmlFetcher: 404 detected on attempt {$attempt} for {$url}");
                    return $resp;
                }

                if ($this->isGoodResponse($resp['code'] ?? 0, $resp['body'] ?? '')) {
                    return $resp;
                }

                usleep((int)(($backoffBase * (2 ** ($attempt - 1)) + rand(100, 600) / 1000) * 1_000_000));
            } catch (\Throwable $e) {
                Log::warning("HtmlFetcher: request error attempt {$attempt} for {$url} via proxy " . $this->proxyToString($proxy) . " — " . $e->getMessage());
            }
        }

        return $lastResp;
    }

    protected function requestOnce(string $url, $proxy = null): array
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->globalTimeout);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->connectTimeout);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_ENCODING, '');
        curl_setopt($ch, CURLOPT_HEADER, false);

        // Headers
        $ua = $this->randomItem($this->userAgents);
        $lang = $this->randomItem($this->acceptLanguages);
        $referer = $this->randomItem($this->referers);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "User-Agent: {$ua}",
            "Accept-Language: {$lang}",
            "Referer: {$referer}",
        ]);

        // Cookies
        curl_setopt($ch, CURLOPT_COOKIEJAR, $this->cookieFile);
        curl_setopt($ch, CURLOPT_COOKIEFILE, $this->cookieFile);

        // Proxy
        if ($proxy) {
            $auth = $proxy->username && $proxy->password ? "{$proxy->username}:{$proxy->password}" : null;
            $scheme = $proxy->scheme ?? 'http';
            $proxyStr = "{$scheme}://{$proxy->host}:{$proxy->port}";
            curl_setopt($ch, CURLOPT_PROXY, $proxyStr);
            if ($auth) curl_setopt($ch, CURLOPT_PROXYUSERPWD, $auth);
        }

        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $httpCode = $info['http_code'] ?? 0;
        curl_close($ch);

        $body = $response ?: '';

        return ['code' => $httpCode, 'body' => $body];
    }

    protected function fetchWithHeadlessBrowser(string $url): ?string
    {
        $nodeScript = base_path('node/fetch.cjs');
        if (!file_exists($nodeScript)) {
            Log::error("HtmlFetcher: node script not found at {$nodeScript}");
            return null;
        }

        $timeoutMs = (int)($this->globalTimeout * 1000);
        $proxies = $this->sortProxiesBySuccess($this->getActiveProxies());
        $triedProxyIds = [];
        $attempts = 0;

        // helper-функция для запуска Node.js скрипта
        $runCommand = function (string $nodeScript, string $url, ?string $proxyArg = null, int $timeoutMs = null) {
            $parts = [
                'node',
                escapeshellarg($nodeScript),
                escapeshellarg($url)
            ];

            if ($proxyArg) {
                $parts[] = '--proxy=' . escapeshellarg($proxyArg);
            }

            if ($timeoutMs !== null) {
                $parts[] = '--timeout=' . intval($timeoutMs);
            }

            $command = implode(' ', $parts);
            $output = [];
            $exitCode = null;
            exec($command, $output, $exitCode);

            return ['exit' => $exitCode, 'body' => implode("\n", $output)];
        };

        // 🧭 1. Попытки с прокси
        if (!empty($proxies)) {
            foreach ($proxies as $proxy) {
                $proxyKey = $this->proxyCacheKey($proxy);
                if (Cache::has($proxyKey)) {
                    continue;
                }

                $attempts++;
                $triedProxyIds[] = $proxy->id ?? ($proxy->host . ':' . $proxy->port);

                $scheme = $proxy->scheme ?? 'http';
                $auth = '';
                if (!empty($proxy->username) && !empty($proxy->password)) {
                    $user = rawurlencode($proxy->username);
                    $pass = rawurlencode($proxy->password);
                    $auth = "{$user}:{$pass}@";
                }
                $proxyStr = "{$scheme}://{$auth}{$proxy->host}:{$proxy->port}";

                Log::info("HtmlFetcher: trying headless browser via proxy {$proxyStr}");

                try {
                    $res = $runCommand($nodeScript, $url, $proxyStr, $timeoutMs);

                    if ($res['exit'] === 0 && !empty($res['body'])) {
                        $this->increaseProxySuccess($proxy);
                        Log::info("HtmlFetcher: headless fetch succeeded via proxy {$proxy->host}:{$proxy->port}");
                        return $res['body'];
                    }

                    // если неуспешно
                    $this->decreaseProxySuccess($proxy);
                    Log::warning("HtmlFetcher: headless fetch failed via proxy {$proxy->host}:{$proxy->port} (exit {$res['exit']})");

                    if ($res['exit'] !== 0 || empty($res['body'])) {
                        Cache::put($proxyKey, true, now()->addMinutes(30)); // blacklist proxy
                    }
                } catch (\Throwable $e) {
                    Log::warning("HtmlFetcher: exception with proxy {$this->proxyToString($proxy)} — " . $e->getMessage());
                    $this->decreaseProxySuccess($proxy);
                    Cache::put($proxyKey, true, now()->addMinutes(30));
                }

                if ($attempts >= $this->maxProxySwitches) {
                    Log::info("HtmlFetcher: reached maxProxySwitches ({$this->maxProxySwitches}), stopping proxy attempts.");
                    break;
                }
            }
        }

        // 🧭 2. Попытка напрямую (без прокси)
        Log::info("HtmlFetcher: trying headless fetch WITHOUT proxy for {$url}");

        try {
            $res = $runCommand($nodeScript, $url, null, $timeoutMs);

            if ($res['exit'] === 0 && !empty($res['body'])) {
                Log::info("HtmlFetcher: headless fetch succeeded without proxy for {$url}");
                return $res['body'];
            }

            Log::warning("HtmlFetcher: headless fetch without proxy failed (exit {$res['exit']})");
        } catch (\Throwable $e) {
            Log::error("HtmlFetcher: exception running headless fetch without proxy — " . $e->getMessage());
        }

        Log::error("HtmlFetcher: all headless browser attempts failed for {$url}. Proxies tried: " . implode(',', $triedProxyIds));
        return null;
    }

    protected function humanDelayBeforeRequest(): void
    {
        usleep(rand(500, 1500) * 1000);
    }

    protected function getActiveProxies(): array
    {
        return Proxy::where('is_active', true)->get()->all();
    }

    protected function sortProxiesBySuccess(array $proxies): array
    {
        return collect($proxies)
            ->sortByDesc(fn($p) => Cache::get("proxy_success:{$p->id}", 0))
            ->values()
            ->all();
    }

    protected function increaseProxySuccess($proxy): void
    {
        $key = "proxy_success:{$proxy->id}";
        Cache::increment($key);
    }

    protected function decreaseProxySuccess($proxy): void
    {
        $key = "proxy_success:{$proxy->id}";
        Cache::decrement($key);
    }

    protected function proxyCacheKey($proxy): string
    {
        return "proxy_blacklist:{$proxy->id}";
    }

    protected function proxyToString($proxy): string
    {
        return $proxy ? ($proxy->host . ':' . $proxy->port) : 'direct';
    }

    protected function randomItem(array $arr)
    {
        return $arr[array_rand($arr)];
    }

    protected function isGoodResponse(int $code, string $body): bool
    {
        return $code === 200 && !empty($body);
    }

    protected function isHardFailure(int $code, string $body): bool
    {
        return $code >= 400 || empty($body);
    }
}

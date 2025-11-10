<?php

namespace App\Services\Parser;

use App\Models\Proxy;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ProxyManager — управляет выбором и оценкой успешности прокси.
 */
class ProxyManager
{
    protected int $maxProxySwitches = 6;

    /**
     * Получить активные прокси, отсортированные по успешности
     */
    public function getActiveSorted(): array
    {
        $proxies = Proxy::where('is_active', true)->get()->all();

        return collect($proxies)
            ->sortByDesc(fn($p) => Cache::get($this->successKey($p), 0))
            ->values()
            ->all();
    }

    /**
     * Отметить успешное использование прокси
     */
    public function markSuccess($proxy): void
    {
        Cache::increment($this->successKey($proxy));
    }

    /**
     * Отметить неудачное использование прокси
     */
    public function markFailure($proxy): void
    {
        Cache::decrement($this->successKey($proxy));
    }

    /**
     * Проверить, не находится ли прокси в "чёрном списке"
     */
    public function isBlacklisted($proxy): bool
    {
        return Cache::has($this->blacklistKey($proxy));
    }

    /**
     * Временно заблокировать прокси (например, если ошибка сети)
     */
    public function blacklist($proxy, int $minutes = 30): void
    {
        Cache::put($this->blacklistKey($proxy), true, now()->addMinutes($minutes));
    }

    /**
     * Сформировать строку для CURL
     */
    public function proxyToCurlString($proxy): string
    {
        $scheme = $proxy->scheme ?? 'http';
        $auth = '';

        if (!empty($proxy->username) && !empty($proxy->password)) {
            $auth = "{$proxy->username}:{$proxy->password}@";
        }

        return "{$scheme}://{$auth}{$proxy->host}:{$proxy->port}";
    }

    /**
     * Получить уникальный ключ для кэша
     */
    protected function successKey($proxy): string
    {
        return "proxy_success:{$proxy->id}";
    }

    protected function blacklistKey($proxy): string
    {
        return "proxy_blacklist:{$proxy->id}";
    }
}

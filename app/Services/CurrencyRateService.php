<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class CurrencyRateService
{
    protected string $baseUrl = 'https://www.floatrates.com/daily';

    /**
     * Получить все курсы относительно USD (кэш на 1 час)
     */
    public function getRates(string $currency): array
    {
        $currency = strtolower($currency);

        return Cache::remember("currency_rates:{$currency}", now()->addHour(), function () use ($currency) {
            $response = Http::timeout(10)->get("{$this->baseUrl}/{$currency}.json");

            if (!$response->ok()) return [];

            return $response->json() ?? [];
        });
    }

    public function rate(string $fromCurrency, string $toCurrency): ?float
    {
        $fromCurrency = strtolower($fromCurrency);
        $toCurrency = strtolower($toCurrency);

        if ($fromCurrency === $toCurrency) return 1;

        $rates = $this->getRates($fromCurrency);

        return $rates[strtolower($toCurrency)]['rate'] ?? null;
    }
}

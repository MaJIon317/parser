<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class CurrencyService
{
    /**
     * Получить все доступные валюты
     *
     * @return Collection
     */
    public function getAvailableCurrencies(): Collection
    {
        return Cache::remember('currencies.all', now()->addWeek(), function () {
            return Currency::all();
        });
    }

    /**
     * Форматировать сумму с кодом валюты
     *
     * @param float $amount
     * @param Currency|null $currency
     * @return string
     */
    public function format(float $amount, ?Currency $currency = null): string
    {
        $defaultCurrency = config('app.currency');

        $currency ??= $this->getAvailableCurrencies()->firstWhere('code', $defaultCurrency) ?? Currency::create(['code' => $defaultCurrency]);

        $amount = number_format($amount, 2);

        return "{$amount} {$currency->code}";
    }

    /**
     * Получить курс валюты относительно USD (или другой базовой)
     *
     * Автообновление и кэш на 1 час
     *
     * @param string $fromCurrency
     * @param string|null $toCurrency
     * @return float|null
     */
    public function rate(string $fromCurrency, ?string $toCurrency = null): ?float
    {
        $fromCurrency = strtoupper($fromCurrency);
        $toCurrency = strtoupper($toCurrency ?? config('app.currency'));

        $cacheKey = "currency_rate:{$fromCurrency}_{$toCurrency}";

        return Cache::remember($cacheKey, now()->addHour(), function () use ($fromCurrency, $toCurrency) {
            $rate = (new CurrencyRateService)->rate($fromCurrency, $toCurrency);

            Log::warning("Couldn\'t get the currency exchange rate: {$fromCurrency}_{$toCurrency}");

            return $rate;
        });
    }

    /**
     * Конвертировать сумму в нужную валюту
     *
     * @param float $amount
     * @param string $fromCurrency
     * @param string $toCurrency
     * @return float|null
     */
    public function convert(float $amount, string $fromCurrency, string $toCurrency = 'USD'): ?float
    {
        $rate = $this->rate($toCurrency, $fromCurrency);

        if ($rate === null) return null;

        return $amount * $rate;
    }
}

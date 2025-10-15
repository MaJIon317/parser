<?php

namespace App\Services;

use App\Models\Currency;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

class CurrencyService
{
    public function getAvailableCurrencies(): Collection
    {
        return Cache::remember('currencies.all', now()->addWeek(), function () {
            return Currency::all();
        });
    }

    public function format(float $amount, Currency $currency): string
    {
        return "{$amount} {$currency->code}";
    }
}

<?php

namespace App\Observers;

use App\Models\Currency;
use Illuminate\Support\Facades\Cache;

class CurrencyObserver
{
    /**
     * Handle the Currency "created" event.
     */
    public function created(Currency $currency): void
    {
        Cache::forget('currencies.all');
    }

    /**
     * Handle the Currency "updated" event.
     */
    public function updated(Currency $currency): void
    {
        Cache::forget('currencies.all');
    }

    /**
     * Handle the Currency "deleted" event.
     */
    public function deleted(Currency $currency): void
    {
        Cache::forget('currencies.all');
    }

    /**
     * Handle the Currency "restored" event.
     */
    public function restored(Currency $currency): void
    {
        Cache::forget('currencies.all');
    }

    /**
     * Handle the Currency "force deleted" event.
     */
    public function forceDeleted(Currency $currency): void
    {
        Cache::forget('currencies.all');
    }
}

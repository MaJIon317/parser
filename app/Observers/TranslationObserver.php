<?php

namespace App\Observers;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

class TranslationObserver
{
    /**
     * Handle the Translation "created" event.
     */
    public function created(Translation $translation): void
    {
        //
    }

    /**
     * Handle the Translation "updated" event.
     */
    public function updated(Translation $translation): void
    {
        $hash = md5($translation->source);
        Cache::put("translate:{$hash}_{$translation->from_lang}_{$translation->to_lang}", $translation->target, now()->addMonth());
    }

    /**
     * Handle the Translation "deleted" event.
     */
    public function deleted(Translation $translation): void
    {
        $hash = md5($translation->source);
        Cache::forget("translate:{$hash}_{$translation->from_lang}_{$translation->to_lang}");
    }

    /**
     * Handle the Translation "restored" event.
     */
    public function restored(Translation $translation): void
    {
        //
    }

    /**
     * Handle the Translation "force deleted" event.
     */
    public function forceDeleted(Translation $translation): void
    {
        //
    }
}

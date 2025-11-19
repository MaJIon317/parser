<?php

namespace App\Observers;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

class TranslationObserver
{
    public function created(Translation $translation): void
    {
        Cache::put(
            $this->cacheKey($translation),
            $translation->target,
            now()->addMonth()
        );
    }

    public function updated(Translation $translation): void
    {
        Cache::put(
            $this->cacheKey($translation),
            $translation->target,
            now()->addMonth()
        );
    }

    public function deleted(Translation $translation): void
    {
        Cache::forget($this->cacheKey($translation));
    }

    public function cacheKey(Translation $translation): string
    {
        return "translate:{$translation->hash}_{$translation->lang}";
    }
}

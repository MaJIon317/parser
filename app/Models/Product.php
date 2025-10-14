<?php

namespace App\Models;

use App\Observers\ProductObserver;
use App\Services\ProductWithdrawalService;
use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'donor_id',
        'code',
        'url',
        'price',
        'detail',
        'images',
        'parsing_status',
        'status',
        'last_parsing',
        'errors',
    ];

    public $casts = [
        'detail' => 'array',
        'images' => 'array',
        'errors' => 'array',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function currency(): HasOneThrough
    {
        return $this->hasOneThrough(
            Currency::class,
            Donor::class,
            'id',
            'id',
            'donor_id',
            'currency_id'
        );
    }

    public function getImagePathsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($path) => asset($path))
            ->toArray();
    }

    public function getTranslationAttribute(): array
    {
        $defaultLocale = config('app.fallback_locale');
        $locale = $this->donor->setting['language'] ?? $defaultLocale;

        return (new ProductWithdrawalService($locale, $defaultLocale))->getTranslatedProduct($this->toArray());
    }

    public function getTranslationAttributesAttribute(): array
    {
        return collect($this->translation['attributes'] ?? [])
            ->mapWithKeys(fn ($item) => [$item['key'] => $item['value']])
            ->toArray();
    }
}

<?php

namespace App\Models;

use App\Facades\Currency as CurrencyFacade;
use App\Observers\ProductObserver;
use App\Services\ProductWithdrawalService;
use App\Traits\HasUuid;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[ObservedBy([ProductObserver::class])]
class Product extends Model
{
    use HasUuid;

    protected $fillable = [
        'uuid',
        'donor_id',
        'category_id',
        'code',
        'url',
        'price',
        'currency_id',
        'object',
        'detail',
        'parsing_status',
        'status',
        'last_parsing',
        'errors',
    ];

    public $casts = [
        'detail' => 'array',
        'errors' => 'array',
    ];

    protected function serializeDate(DateTimeInterface $date): string
    {
        return $date->format('Y-m-d H:i:s');
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class);
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|\App\Models\ProductLog[]
     */
    public function logs(): HasMany
    {
        return $this->hasMany(ProductLog::class);
    }

    public function getConvertPriceAttribute():   ?string
    {
        return CurrencyFacade::convert($this->price, $this->currency->code, config('app.currency'));
    }

    public function getFormattedPriceAttribute():   ?string
    {
        return CurrencyFacade::format($this->convert_price);
    }

    public function priceConvert(string $currency_code, ?float $newPrice = null): float
    {
        return CurrencyFacade::convert($newPrice ?: $this->price, $this->currency->code, $currency_code);
    }

    public function getImagePathsAttribute(): array
    {
        return collect($this->images ?? [])
            ->map(fn ($path) => asset($path))
            ->toArray();
    }

    public function isTranslation(?string $toLang = null): array
    {
        return (new ProductWithdrawalService(
                    $this->donor->setting['language'] ?? config('app.fallback_locale'),
                    $toLang))
                ->getTranslatedProduct($this->toArray());
    }

    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class);
    }
}

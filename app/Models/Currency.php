<?php

namespace App\Models;

use App\Observers\CurrencyObserver;
use App\Services\CurrencyService;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([CurrencyObserver::class])]
class Currency extends Model
{
    /** @use HasFactory<\Database\Factories\CurrencyFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
    ];

    public function getRateAttribute(): float
    {
        return (new CurrencyService)->rate($this->code);
    }
}

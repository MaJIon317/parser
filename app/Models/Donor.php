<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    protected $fillable = [
        'currency_id',
        'name',
        'code',
        'rate_limit',
        'delay_min',
        'delay_max',
        'refresh_interval',
        'refresh_interval_sale',
        'is_active',
        'setting'
    ];

    public $casts = [
        'is_active' => 'boolean',
        'setting' => 'array',
    ];

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(DonorLog::class);
    }
}

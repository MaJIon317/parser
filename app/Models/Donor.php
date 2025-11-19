<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Donor extends Model
{
    protected $fillable = [
        'name',
        'code',
        'rate_limit',
        'delay_min',
        'delay_max',
        'refresh_interval',
        'refresh_interval_sale',
        'is_active',
        'pages',
        'setting',
        'parsing_status',
    ];

    public $casts = [
        'is_active' => 'boolean',
        'pages' => 'array',
        'setting' => 'array',
    ];

    public function listings(): HasMany
    {
        return $this->hasMany(DonorListing::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}

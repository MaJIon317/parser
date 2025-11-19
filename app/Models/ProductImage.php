<?php

namespace App\Models;

use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class ProductImage extends Model
{
    protected $fillable = [
        'product_id',
        'url',
        'correct_url',
        'hashed',
    ];

    public $casts = [
        'correct_url' => 'array',
        'hashed' => 'array',
    ];

    public function storage(): Filesystem
    {
        return Storage::disk('s3');
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function getSettingWatermarkAttribute(): array
    {
        return (array) $this->product->donor->watermark ?? [];
    }
}

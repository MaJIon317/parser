<?php

namespace App\Models;

use App\Traits\HasRequestId;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ProductLog extends Model
{
    use HasRequestId;

    protected $fillable = [
        'request_id',
        'product_id',
        'model_type',
        'model_id',
        'type',
        'code',
        'message',
        'data',
    ];

    public $casts = [
        'message' => 'string',
        'data' => 'array',
    ];

    public function product(): belongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function model(): MorphTo
    {
        return $this->morphTo();
    }
}


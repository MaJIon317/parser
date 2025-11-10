<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebhookLog extends Model
{
    protected $fillable = [
        'webhook_id',
        'product_id',
        'message',
        'data',
    ];

    public $casts = [
        'message' => 'string',
        'data' => 'array',
    ];

    public function webhook(): BelongsTo
    {
        return $this->belongsTo(Webhook::class);
    }

    public function product(): belongsTo
    {
        return $this->belongsTo(Product::class);
    }
}


<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\MassPrunable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Logging extends Model
{
    use MassPrunable;

    protected $fillable = [
        'type',
        'donor_id',
        'product_id',
        'url',
        'parser_class',
        'status',
        'message',
        'context',
        'started_at',
        'finished_at',
        'duration_ms',
        'job_uuid',
        'queue',
    ];

    protected $casts = [
        'context' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];

    public function prunable(): Builder
    {
        return static::where('created_at', '<=', now()->subWeeks(2));
    }

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}

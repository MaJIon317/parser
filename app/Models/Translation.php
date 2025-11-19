<?php

namespace App\Models;

use App\Observers\TranslationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[ObservedBy([TranslationObserver::class])]
class Translation extends Model
{
    protected $fillable = [
        'hash',
        'lang',
        'source',
        'target',
        'target_hash',
        'target_text',
        'canonical_id',
    ];

    public function canonical(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_id');
    }
}

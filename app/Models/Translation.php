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
        'source',
        'target',
        'from_lang',
        'to_lang',
        'normalized_hash',
        'normalized_text',
        'canonical_id',
    ];

    public function canonical(): BelongsTo
    {
        return $this->belongsTo(self::class, 'canonical_id');
    }
}

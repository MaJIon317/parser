<?php

namespace App\Models;

use App\Observers\TranslationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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

    public function translations(): HasMany
    {
        return $this->hasMany(TranslationVariant::class);
    }
}

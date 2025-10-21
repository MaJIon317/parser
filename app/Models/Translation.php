<?php

namespace App\Models;

use App\Observers\TranslationObserver;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Model;

#[ObservedBy([TranslationObserver::class])]
class Translation extends Model
{
    protected $fillable = [
        'hash',
        'source',
        'target',
        'from_lang',
        'to_lang',
    ];
}

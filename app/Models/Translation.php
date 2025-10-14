<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

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

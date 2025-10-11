<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Proxy extends Model
{
    protected $fillable = [
        'host',
        'port',
        'username',
        'password',
        'scheme',
        'is_active',
        'note',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}

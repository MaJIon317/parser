<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Product extends Model
{
    protected $fillable = [
        'uuid',
        'donor',
        'code',
        'name',
        'data',
        'images',
        'parsing_status',
        'status',
    ];

    public $casts = [
        'data' => 'array',
        'images' => 'array',
    ];
}

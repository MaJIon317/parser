<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Donor extends Model
{
    protected $fillable = [
        'name',
        'base_url',
        'rate_limit',
        'delay_min',
        'delay_max',
        'refresh_interval',
        'user_agent',
        'is_active'
    ];
}

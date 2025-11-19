<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DonorListing extends Model
{
    protected $fillable = [
        'donor_id',
        'url',
        'is_parsed',
    ];

    public function donor(): BelongsTo
    {
        return $this->belongsTo(Donor::class);
    }
}

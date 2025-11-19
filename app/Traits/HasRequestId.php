<?php

namespace App\Traits;

use Illuminate\Support\Str;

trait HasRequestId
{
    protected static function bootHasRequestId(): void
    {
        static::creating(function ($model) {
            if (empty($model->request_id)) {
                $model->request_id = (string) Str::uuid();
            }
        });
    }
}

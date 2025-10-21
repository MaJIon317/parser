<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class ProductResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return Arr::except([
            ...$this->isTranslation(
                in_array($request->input('locale'), array_keys(config('app.locales'))) ? $request->input('locale') : null,
            ),

            'images' => $this->images,
            'uuid' => $this->uuid,
            'code' => $this->code,
            'object' => $this->category?->name,
            'currency' => $this->currency?->code,
        ], [
            'id',
            'parsing_status',
            'donor_id',
            'category_id',
            'currency_id',
            'donor',
            'errors',
            'created_at'
        ]);
    }
}

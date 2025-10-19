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
            /*
            ...$this->isTranslation(
                in_array($request->input('locale'), array_keys(config('app.locales'))) ? $request->input('locale') : null,
            ),
            */
            'uuid' => $this->uuid,
            'code' => $this->code,
            'url' => $this->url,
            'detail' => $this->detail,
            'images' => $this->images,
            'object' => $this->category?->name,
            'price' => $this->price,
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

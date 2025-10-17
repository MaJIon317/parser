<?php

namespace App\Services\Parser;

use App\Facades\Currency;
use App\Models\Donor;
use App\Models\Product;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProductSaver
{
    protected Collection $currencies;

    protected ImageDownloader $imageDownloader;

    public function __construct() {
        $this->currencies = Currency::getAvailableCurrencies();
        $this->imageDownloader = new ImageDownloader();
    }

    public function saveList(Donor $donor, array $products): array
    {
        $saved = [];
        $skipped = [];

        foreach ($products as $data) {
            $validator = Validator::make($data, [
                'url' => 'required|url',
                'code' => 'required|string|max:255',
                'category_id' => 'exists:categories,id',
                'price' => 'nullable|numeric|min:0',
                'currency' => 'exists:currencies,code',
            ]);

            if ($validator->fails()) {
                $skipped[] = [
                    ...$data,
                    'errors' => $validator->errors()->all()
                ];

                continue;
            }

            $currency_id = $this->currencies->where('code', $data['currency'] ?? null)->first()?->id;

            if (!empty($data['price'])) {
                $product = Product::updateOrCreate(
                    [
                        'donor_id' => $donor->id,
                        'code' => $data['code'],
                    ],
                    [
                        'url' => $data['url'],
                        'category_id' => $data['category_id'],
                        'price' => $data['price'],
                        'currency_id' => $currency_id,
                        'status' => isset($data['status']) && !$data['status'] ? 'inactive' : 'active',
                    ]
                );
            } else {
                // Только обновление существующего товара
                Product::where('donor_id', $donor->id)
                    ->where('code', $data['code'])
                    ->update([
                        'status' => 'inactive',
                    ]);
            }

            $saved[] = $product;
        }

        return compact('saved', 'skipped');
    }

    public function save(Product $product, array $data): array
    {
        $validator = Validator::make($data, [
            'detail' => 'required|array',
            'detail.name' => 'required|string|max:500',
            'detail.category' => 'nullable|string|max:255',
            'images' => 'nullable|array',
            'images.*' => 'nullable|url',
            'errors' => 'nullable|array',
        ]);

        if ($validator->fails()) {
            $product->update([
                'errors' => $validator->errors()->all(),
            ]);

            return $product->toArray();
        }

        // Скачиваем изображения
        foreach ($data['images'] ?? [] as $key => $image) {
            $data['images'][$key] = $this->imageDownloader->download($image, $product->uuid);
        }

        $product->update([
            'detail' => $data['detail'] ?? null,
            'images' => $data['images'] ?? null,
            'parsing_status' => 'completed',
            'last_parsing' => now(),
            'errors' => $data['errors'] ?? null,
        ]);

        return [
            'status' => 'success',
            'product' => $product->toArray(),
        ];
    }
}

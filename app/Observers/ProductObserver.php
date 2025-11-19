<?php

namespace App\Observers;

use App\Jobs\ParseProductJob;
use App\Models\Product;
use App\Models\Translation;

class ProductObserver
{
    /**
     * Handle the Product "created" event.
     */
    public function created(Product $product): void
    {
        // Запускаем парсинг нового товара
        ParseProductJob::dispatch($product);

        $product->logs()->create([
            'model_type' => $product->donor->getMorphClass(),
            'model_id' => $product->donor->id,
            'code' => 'created',
            'message' => 'Product Сreated'
        ]);
    }

    /**
     * Handle the Product "updated" event.
     */
    public function updated(Product $product): void
    {
        if (!$product->detail || $product->parsing_status === 'new') {
            ParseProductJob::dispatch($product);
        }

        $product->logs()->create([
            'model_type' => $product->donor->getMorphClass(),
            'model_id' => $product->donor->id,
            'code' => 'updated',
            'message' => 'Product Updated'
        ]);


        // Добавляем атрибуты в перевод
        if ($product->detail) {
            $details = flattenKeysAndValuesFlexible($product->detail, [ // Исключаем ключи
                'sku', 'name', 'category', 'description', 'attributes',
            ], [ // Исключаем значения по ключу
                'sku',
            ]);

            foreach ($details as $detail) {
                $hash = md5($detail);

                Translation::firstOrCreate(
                    ['hash' => $hash],
                    [
                        'lang' => $product->donor->setting['language'] ?? config('app.fallback_locale'),
                        'source' => $detail,
                    ]
                );
            }
        }

    }

    /**
     * Handle the Product "deleted" event.
     */
    public function deleted(Product $product): void
    {
        $product->logs()->create([
            'model_type' => $product->donor->getMorphClass(),
            'model_id' => $product->donor->id,
            'code' => 'deleted',
            'message' => 'Product Deleted',
            'data' => $product->detail,
        ]);
    }
}

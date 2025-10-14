<?php

namespace App\Services\ParserNew;

use App\Models\Donor;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;
use Exception;

/**
 * ProductParser — фабрика для автоматического выбора нужного парсера
 */
class ProductParser
{
    /**
     * Упрощённый вызов: сразу парсит список товаров.
     */
    public static function parseList(Donor $donor, bool $test = false): BaseParser
    {
        $code = trim($donor->code ?? '');

        if (!$code) {
            throw new Exception("У донора [{$donor->id}] не указан код (code)");
        }

        // Преобразуем код в имя класса, например "vipstation_com" → "VipstationComParser"
        $classBaseName = Str::studly($code) . 'Parser';

        // Получаем namespace текущего класса и добавляем поддиректорию Donors
        $baseNamespace = static::class;
        $baseNamespace = substr($baseNamespace, 0, strrpos($baseNamespace, '\\')); // App\Services\ParserNew
        $classFullName = "{$baseNamespace}\\Donors\\{$classBaseName}";

        if (!class_exists($classFullName)) {
            throw new Exception("Класс парсера не найден: {$classFullName}");
        }

        $fetcher = new HtmlFetcher($donor);

        return new $classFullName(
            donor: $donor,
            fetcher: $fetcher,
            test: $test
        );
    }

    /**
     * Упрощённый вызов: сразу парсит товар.
     */
    public static function parseItem(Product $product, bool $test = false): BaseParser
    {
        if (!$product->donor) {
            throw new Exception("У продукта [{$product->id}] отсутствует донор");
        }

        $code = trim($product->donor->code ?? '');
        if (!$code) {
            throw new Exception("У донора продукта [{$product->id}] не указан код (code)");
        }

        $classBaseName = Str::studly($code) . 'Parser';

        // динамически определяем namespace, как в parseList
        $baseNamespace = static::class;
        $baseNamespace = substr($baseNamespace, 0, strrpos($baseNamespace, '\\'));
        $classFullName = "{$baseNamespace}\\Donors\\{$classBaseName}";

        if (!class_exists($classFullName)) {
            throw new Exception("Класс парсера не найден: {$classFullName}");
        }

        $fetcher = new HtmlFetcher($product->donor);

        return new $classFullName(
            product: $product,
            fetcher: $fetcher,
            test: $test
        );
    }
}

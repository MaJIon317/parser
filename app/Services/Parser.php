<?php

namespace App\Services;

use App\Models\Donor;
use App\Models\Product;
use App\Services\Parser\BaseParser;
use App\Services\Parser\HtmlFetcher;
use Exception;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

/**
 * Parser — фабрика для автоматического выбора нужного парсера
 */
class Parser
{
    /**
     * Возвращает список доступных доноров с кэшированием
     */
    public static function availableDonors(bool $forSelect = false): array
    {
        $available = Cache::remember('parser.available_donors', now()->addHours(1), function () {
            $baseNamespace = static::class;
            $baseNamespace = substr($baseNamespace, 0, strrpos($baseNamespace, '\\')); // App\Services\ParserNew

            $relativePath = str_replace(['\\', 'App/'], ['/', ''], $baseNamespace . '/Parser/Donors');
            $dir = app_path($relativePath);

            if (!File::exists($dir)) {
                return [];
            }

            $files = File::files($dir);
            $available = [];

            foreach ($files as $file) {
                $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $classFull = "{$baseNamespace}\\Parser\\Donors\\{$className}";

                if (!class_exists($classFull)) {
                    continue;
                }

                // Преобразуем "VipstationComParser" → "vipstation_com"
                $code = Str::of($className)
                    ->replaceLast('Parser', '')
                    ->snake()
                    ->value();

                // Извлекаем protected string $baseUrl
                $baseUrl = null;
                try {
                    $ref = new \ReflectionClass($classFull);
                    if ($ref->hasProperty('baseUrl')) {
                        $prop = $ref->getProperty('baseUrl');
                        $prop->setAccessible(true);
                        $baseUrl = $prop->isStatic()
                            ? $prop->getValue()
                            : $prop->getValue($ref->newInstanceWithoutConstructor());
                    }
                } catch (\Throwable $e) {
                    $baseUrl = null;
                }

                $available[$code] = [
                    'class' => $classFull,
                    'name'  => Str::headline($code),
                    'baseUrl' => $baseUrl,
                ];
            }

            ksort($available);
            return $available;
        });

        // 🎨 если запросили формат для select — преобразуем красиво
        if ($forSelect) {
            return collect($available)
                ->mapWithKeys(fn($info, $code) => [
                    $code => $info['name'] . ($info['baseUrl'] ? " ({$info['baseUrl']})" : ''),
                ])
                ->toArray();
        }

        return $available;
    }

    /**
     * Создаёт экземпляр парсера для донора или конкретного продукта.
     *
     * @param Donor|Product $target Донор или продукт
     * @return BaseParser
     * @throws Exception
     */
    public static function make(Donor|Product $target): BaseParser
    {
        if ($target instanceof Product) {
            $product = $target;
            $donor = $product->donor;

            if (!$donor) {
                throw new Exception("У продукта [{$product->id}] отсутствует донор");
            }
        } else {
            $donor = $target;
            $product = null;
        }

        $code = trim($donor->code ?? '');

        if (!$code) {
            $type = $product ? "донора продукта [{$product->id}]" : "донора [{$donor->id}]";
            throw new Exception("У {$type} не указан код (code)");
        }

        $available = static::availableDonors();

        if (!isset($available[$code])) {
            throw new Exception("Парсер для донора '{$code}' не найден в зарегистрированных классах.");
        }

        $parserClass = $available[$code]['class'];
        $fetcher = new HtmlFetcher($donor);

        return new $parserClass(
            fetcher: $fetcher,
            donor: $donor,
            product: $product
        );
    }
}

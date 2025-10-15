<?php

namespace App\Services;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

class ProductWithdrawalService
{
    protected string $fromLang;
    protected string $toLang;
    protected int $batchSize = 50;
    protected bool $status = true;

    public function __construct(?string $fromLang = null, ?string $toLang = null)
    {
        $defaultLocale = config('app.fallback_locale');

        $this->fromLang = $fromLang ?? $defaultLocale;
        $this->toLang = $toLang ?? $defaultLocale;
    }

    /**
     * Перевод массива: значения ключей detail, ключи + значения attributes
     */
    protected function translateProductDetail(array $detail, int $product_id): array
    {
        $translated = [];

        foreach ($detail as $key => $value) {
            if ($key === 'attributes' && is_array($value)) {
                // Рекурсивно переводим ключи и значения attributes
                $translated[$key] = $this->translateArrayKeysAndValues($value, $product_id);
            } elseif (is_string($value)) {
                // Только значения для остальных полей detail
                $translated[$key] = $this->translateString($value, $product_id);
            } elseif (is_array($value)) {
                // Массивы, кроме attributes, переводим рекурсивно только значения
                $translated[$key] = $this->translateArrayValues($value, $product_id);
            } else {
                $translated[$key] = $value;
            }
        }

        return $translated;
    }

    /**
     * Рекурсивный перевод ключей и значений массива
     */
    protected function translateArrayKeysAndValues(array $data, int $product_id): array
    {
        $translated = [];
        foreach ($data as $key => $value) {
            // убираем лишние кавычки
            $translatedKey = is_string($key) ? trim($this->translateString($key, $product_id), "\"' ") : $key;

            if (is_string($value)) {
                $translated[$translatedKey] = trim($this->translateString($value, $product_id), "\"' ");
            } elseif (is_array($value)) {
                $translated[$translatedKey] = $this->translateArrayKeysAndValues($value, $product_id);
            } else {
                $translated[$translatedKey] = $value;
            }
        }
        return $translated;
    }

    /**
     * Рекурсивный перевод только значений массива
     */
    protected function translateArrayValues(array $data, int $product_id): array
    {
        $translated = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $translated[$key] = trim($this->translateString($value, $product_id), "\"' ");
            } elseif (is_array($value)) {
                $translated[$key] = $this->translateArrayValues($value, $product_id);
            } else {
                $translated[$key] = $value;
            }
        }
        return $translated;
    }

    /**
     * Перевод одной строки с кэшированием и сохранением в БД
     */
    protected function translateString(string $text, int $product_id): string
    {
        $hash = md5($text);
        $cacheKey = "product:{$product_id}:translate:{$hash}_{$this->fromLang}_{$this->toLang}";

        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        $existing = Translation::where('hash', $hash)
            ->where('from_lang', $this->fromLang)
            ->where('to_lang', $this->toLang)
            ->first();

        if ($existing) {
            Cache::put($cacheKey, $existing->target, now()->addMonth());
            return $existing->target;
        }

        try {
            $prompt = <<<PROMPT
You are a professional translator.
Translate the following text from {$this->fromLang} to {$this->toLang}.
Keep formatting and HTML if present.
Text: "{$text}"
PROMPT;

            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $text],
                ],
            ]);

            $translated = trim($response->choices[0]->message->content ?? $text);

            Translation::updateOrCreate(
                ['hash' => $hash, 'from_lang' => $this->fromLang, 'to_lang' => $this->toLang],
                ['source' => $text, 'target' => $translated]
            );

            Cache::put($cacheKey, $translated, now()->addMonth());

            return $translated;
        } catch (\Throwable $e) {
            Log::error("ProductWithdrawalService: translation failed — {$e->getMessage()}");
            $this->status = false;
            return $text;
        }
    }

    /**
     * Основной метод для перевода всего продукта
     */
    public function getTranslatedProduct(array $productData): array
    {
        // Если язык исходный = целевой — ничего не переводим
        if ($this->fromLang === $this->toLang) {
            return [
                ...$productData,
                'status_translation' => true,
            ];
        }

        $detail = $productData['detail'] ?? [];
        $translatedDetail = $this->translateProductDetail($detail, $productData['id'] ?? 0);

        return [
            ...$productData,
            'detail' => $translatedDetail,
            'status_translation' => $this->status,
        ];
    }
}

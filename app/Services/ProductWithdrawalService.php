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
    protected bool $status = true;

    public function __construct(?string $fromLang = null, ?string $toLang = null)
    {
        $defaultLocale = config('app.fallback_locale');
        $this->fromLang = strtolower($fromLang ?? $defaultLocale);
        $this->toLang = strtolower($toLang ?? $defaultLocale);
    }

    /**
     * Получаем продукт с переводами и сохраняем все ключи/значения
     */
    public function getTranslatedProduct(array $productData): array
    {
        $detail = $productData['detail'] ?? [];

        // 1️⃣ Сохраняем все ключи и значения исходного продукта
        $this->storeAllKeys($detail);

        // Если языки одинаковые — возвращаем исходные данные
        if (!$this->fromLang || !$this->toLang || ($this->fromLang === $this->toLang)) {
            return [
                ...$productData,
                'status_translation' => true,
            ];
        }

        // 2️⃣ Получаем переводы
        $translatedDetail = $this->translateDetail($detail);

        // 3️⃣ Применяем переводы к массиву
        $applyTranslations = $this->applyTranslations($detail, $translatedDetail);

        return [
            ...$productData,
            'detail' => $applyTranslations,
            'status_translation' => $this->status,
        ];
    }

    /**
     * Сохраняем все ключи и значения рекурсивно
     */
    protected function storeAllKeys(array $data, bool $inAttributes = false): void
    {
        foreach ($data as $key => $value) {
            if ($inAttributes) {
                $this->storeTranslation($key, $key); // ключ как строка
            }

            if (is_array($value)) {
                $this->storeAllKeys($value, $inAttributes || $key === 'attributes');
            } else {
                $this->storeTranslation($value, $value); // значение на исходном языке
            }
        }
    }

    /**
     * Переводим detail, используя OpenAI и кэш/базу
     */
    protected function translateDetail(array $detail): array
    {
        $flat = $this->flattenValues($detail);
        $translate = $this->splitTranslations($flat);

        if ($translate['to_translate']) {
            try {
                $systemPrompt = <<<PROMPT
You are a professional translator AI.
Translate all entries from "{$this->fromLang}" to "{$this->toLang}".
Input is a JSON array of strings.

Rules:
- Translate all the values
- Keep formatting and HTML
- Output single JSON object: {"original":"translated", ...}
- Do not add extra fields
PROMPT;

                $response = OpenAI::chat()->create([
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => json_encode($translate['to_translate'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)],
                    ],
                ]);

                $translatedChunk = json_decode($response->choices[0]->message->content ?? '', true);

                if (json_last_error() === JSON_ERROR_NONE) {
                    foreach ($translatedChunk as $source => $target) {
                        $this->storeTranslation($source, $target);
                        $translate['translated'][$source] = $target;
                    }
                } else {
                    Log::warning("Batch translation returned invalid JSON: " . json_last_error_msg());
                    $this->status = false;
                }
            } catch (\Throwable $e) {
                Log::error("Batch translation failed: {$e->getMessage()}");
                $this->status = false;
            }
        }

        return $translate['translated'];
    }

    /**
     * Применяем переводы рекурсивно
     */
    protected function applyTranslations(array $data, array $translations, bool $inAttributes = false): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            $newKey = $inAttributes && isset($translations[$key]) ? $translations[$key] : $key;

            if (is_array($value)) {
                $result[$newKey] = $this->applyTranslations($value, $translations, $inAttributes || $key === 'attributes');
            } else {
                $result[$newKey] = $translations[$value] ?? $value;
            }
        }

        return $result;
    }

    /**
     * Собираем все текстовые значения и ключи для перевода
     */
    protected function flattenValues(array $data, bool $inAttributes = false): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if ($inAttributes) {
                $flat[] = $key;
            }

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenValues($value, true));
            } else {
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /**
     * Разделяем уже переведённые строки и новые
     */
    protected function splitTranslations(array $flat): array
    {
        $translated = [];
        $toTranslate = [];

        foreach ($flat as $value) {
            $cacheKey = $this->cacheKey($value);

            if (Cache::has($cacheKey['key'])) {
                $translated[$value] = Cache::get($cacheKey['key']);
                continue;
            }

            $existing = Translation::where('hash', $cacheKey['hash'])
                ->where('from_lang', $this->fromLang)
                ->where('to_lang', $this->toLang)
                ->first();

            if ($existing) {
                $translated[$value] = $existing->target;
                Cache::put($cacheKey['key'], $existing->target, now()->addMonth());
            } else {
                $toTranslate[] = $value;
            }
        }

        return [
            'translated' => $translated,
            'to_translate' => $toTranslate,
        ];
    }

    /**
     * Сохраняем перевод или исходное значение
     */
    protected function storeTranslation(string $source, string $target): void
    {
        $cacheKey = $this->cacheKey($source);

        Translation::updateOrCreate(
            [
                'hash' => $cacheKey['hash'],
                'from_lang' => $this->fromLang,
                'to_lang' => $this->toLang,
            ],
            [
                'source' => $source,
                'target' => $target,
            ]
        );

        Cache::put($cacheKey['key'], $target, now()->addMonth());
    }

    protected function cacheKey(string $source): array
    {
        $hash = md5($source);

        return [
            'hash' => $hash,
            'key' => "translate:{$hash}_{$this->fromLang}_{$this->toLang}",
        ];
    }
}

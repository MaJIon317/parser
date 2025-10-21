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

    public function getTranslatedProduct(array $productData): array
    {

        if (!$this->fromLang || !$this->toLang || ($this->fromLang === $this->toLang)) {
            return [
                ...$productData,
                'status_translation' => true,
            ];
        }

        $detail = $productData['detail'] ?? [];

        $translatedDetail = $this->translateDetail($detail);

        $applyTranslations = $this->applyTranslations($detail, $translatedDetail);

        return [
            ...$productData,
            'detail' => $applyTranslations,
            'status_translation' => $this->status,
        ];
    }

    /**
     * Перевод detail с поддержкой перевода ключей внутри attributes
     */
    protected function translateDetail(array $detail): array
    {
        // 1. Флэттим detail в массив объектов: ключи и значения для перевода
        $flat = $this->flattenValues($detail);

        // Ищем переводы
        $translate = $this->splitTranslations($flat);

        // 3. Отправляем недостающие переводы в OpenAI
        if ($translate['to_translate']) {
            try {
                $systemPrompt = <<<PROMPT
You are a professional translator AI.
Translate all entries from "{$this->fromLang}" to "{$this->toLang}".
Input is a JSON array of objects

Rules:
- Translate all the values
- Save formatting and HTML.
- Print a single JSON object where each key is the original string, and the value is its translation.
- Do not wrap each pair in a separate array.
- If it was not possible to translate the string, output the string to the value before the translation.
- DO NOT add additional fields or comments.
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
     * Рекурсивно обновляет массив $data, заменяя строки на переводы из $translations.
     *
     * @param array $data
     * @param array $translations  // ['original string' => 'translated string']
     * @param bool $inAttributes
     * @return array
     */
    protected function applyTranslations(array $data, array $translations, bool $inAttributes = false): array
    {
        $result = [];

        foreach ($data as $key => $value) {
            // Определяем ключ, который нужно перевести (только внутри attributes)
            $newKey = $inAttributes && isset($translations[$key]) ? $translations[$key] : $key;

            if (is_array($value)) {
                // Рекурсивно проходим дальше
                $result[$newKey] = $this->applyTranslations($value, $translations, $inAttributes || $key === 'attributes');
            } else {
                // Подставляем перевод, если есть
                $result[$newKey] = $translations[$value] ?? $value;
            }
        }

        return $result;
    }


    /**
     * Рекурсивно собирает значения и ключи для перевода.
     *
     * Правила:
     * - Первый уровень ключей игнорируется.
     * - Для массивов внутри attributes переводим ключи и значения.
     *
     * @param array $data
     * @param bool $inAttributes
     * @return array
     */
    protected function flattenValues(array $data, bool $inAttributes = false): array
    {
        $flat = [];

        foreach ($data as $key => $value) {
            if ($inAttributes) {
                // Внутри attributes: сначала ключ
                $flat[] = $key;
            }

            if (is_array($value)) {
                $flat = array_merge($flat, $this->flattenValues($value, true));
            } else {
                // добавляем само значение
                $flat[] = $value;
            }
        }

        return $flat;
    }

    /**
     * Разделяет массив на уже переведённые строки и строки для перевода.
     *
     * @param array $flat Плоский массив значений/ключей для перевода
     * @return array ['translated' => [], 'to_translate' => []]
     */
    protected function splitTranslations(array $flat): array
    {
        $translated = [];
        $toTranslate = [];

        foreach ($flat as $value) {
            $cacheKey = $this->cacheKey($value);

            // сначала проверяем кэш
            if (Cache::has($cacheKey['key'])) {
                $translated[$value] = Cache::get($cacheKey['key']);
                continue;
            }

            // проверяем БД
            $existing = Translation::where('hash', $cacheKey['hash'])
                ->where('from_lang', $this->fromLang)
                ->where('to_lang', $this->toLang)
                ->first();

            if ($existing) {
                $translated[$value] = $existing->target;
                Cache::put($cacheKey['key'], $existing->target, now()->addMonth());
            } else {
                // не найдено — нужно переводить
                $toTranslate[] = $value;
            }
        }

        return [
            'translated' => $translated,
            'to_translate' => $toTranslate,
        ];
    }

    protected function storeTranslation(string $source, string $target): void
    {
        $cacheKey = $this->cacheKey($source);

        Translation::updateOrCreate(
            ['hash' => $cacheKey['hash'], 'from_lang' => $this->fromLang, 'to_lang' => $this->toLang],
            ['source' => $source, 'target' => $target]
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

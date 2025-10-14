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

    public function __construct(string $fromLang = 'ja', ?string $toLang = null)
    {
        $this->fromLang = $fromLang;
        $this->toLang = $toLang ?? config('app.fallback_locale');

    }

    /**
     * Подготовка массива текстов для перевода
     */
    public function prepareTexts(array $productData, int $product_id): array
    {
        $items = [];

        if (!empty($productData['name'])) {
            $items[] = [
                'key' => md5($productData['name']),
                'text' => $productData['name'],
            ];
        }

        if (!empty($productData['category'])) {
            $items[] = [
                'key' => $productData['category'],
                'text' => $productData['category'],
            ];
        }

        $attributes = $productData['data']['attributes'] ?? [];
        foreach ($attributes as $k => $v) {
            $items[] = ['key' => md5($k), 'text' => $k];
            $items[] = ['key' => md5($v), 'text' => $v];
        }

        return $items;
    }

    /**
     * Основная функция перевода
     */
    public function translateTexts(array $texts, int $product_id): array
    {
        $results = [];

        foreach (array_chunk($texts, $this->batchSize) as $chunk) {
            $results = array_merge($results, $this->translateChunk($chunk, $product_id));
        }

        return $results;
    }

    protected function translateChunk(array $chunk, int $product_id): array
    {
        $hashes = array_column($chunk, 'key');

        // 🔑 Формируем уникальный ключ кэша (по хэшам и языкам)
        $cacheKey = "product:{$product_id}:translate:" . md5(implode('_', $hashes) . "_{$this->fromLang}_{$this->toLang}");

        // 🧠 Проверяем, есть ли результат в кэше
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // ⚡ Один запрос вместо десятков
        $existingTranslations = Translation::whereIn('hash', $hashes)
            ->where('from_lang', $this->fromLang)
            ->where('to_lang', $this->toLang)
            ->get()
            ->keyBy('hash');

        $toTranslate = [];
        $result = [];

        foreach ($chunk as $item) {
            $hash = $item['key'];
            if ($existingTranslations->has($hash)) {
                $translation = $existingTranslations[$hash];
                $result[] = [
                    'key' => $hash,
                    'text' => $item['text'],
                    'translation' => $translation->target,
                ];
            } else {
                $toTranslate[] = $item;
            }
        }

        if (empty($toTranslate)) {
            Cache::put($cacheKey, $result, now()->addMonth());

            return $result;
        }

        // 🧠 GPT-перевод только для отсутствующих
        $texts = array_column($toTranslate, 'text');
        $joined = implode("\n---\n", $texts);

        $prompt = <<<PROMPT
                        You are a professional translator.
                        Translate each text block from {$this->fromLang} to {$this->toLang}.
                        Keep the same order and formatting.
                        Separate each translation with a line "---".
                        PROMPT;

        try {
            $response = OpenAI::chat()->create([
                'model' => 'gpt-4o-mini',
                'messages' => [
                    ['role' => 'system', 'content' => $prompt],
                    ['role' => 'user', 'content' => $joined],
                ],
            ]);

            $content = trim($response->choices[0]->message->content ?? '');
            $translatedBlocks = array_map('trim', explode('---', $content));

            $toInsert = [];

            foreach ($toTranslate as $i => $item) {
                $translatedText = $translatedBlocks[$i] ?? $item['text'];

                $result[] = [
                    'key' => $item['key'],
                    'text' => $item['text'],
                    'translation' => $translatedText,
                ];

                $toInsert[] = [
                    'hash' => $item['key'],
                    'source' => $item['text'],
                    'target' => $translatedText,
                    'from_lang' => $this->fromLang,
                    'to_lang' => $this->toLang,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }

            // ⚡ Массовая вставка для производительности
            Translation::upsert(
                $toInsert,
                ['hash', 'from_lang', 'to_lang'],
                ['target', 'updated_at']
            );

            Cache::put($cacheKey, $result, now()->addMonth());

        } catch (\Throwable $e) {
            Log::error("TranslateService: translation failed — {$e->getMessage()}");

            foreach ($toTranslate as $item) {
                $result[] = [
                    'key' => $item['key'],
                    'text' => $item['text'],
                    'translation' => $item['text'],
                ];
            }

            $this->status = false;
        }

        return $result;
    }

    /**
     * Формирование нового массива продукта с переведёнными данными
     */
    public function getTranslatedProduct(array $productData): array
    {
        $texts = $this->prepareTexts($productData, $productData['id']);
        $translated = $this->translateTexts($texts, $productData['id']);

        $map = [];
        foreach ($translated as $item) {
            $map[$item['key']] = $item['translation'] ?? $item['text'];
        }

        $name = $productData['name'] ?? '';
        $attributes = $productData['data']['attributes'] ?? [];
        $rebuiltAttributes = [];

        foreach ($attributes as $k => $v) {
            $rebuiltAttributes[] = [
                'key' => $map[md5($k)] ?? $k,
                'value' => $map[md5($v)] ?? $v,
            ];
        }

        return [
            'name' => $map[md5($name)] ?? $name,
            'category' => $productData['category'] ?? '',
            'attributes' => $rebuiltAttributes,
            'status' => $this->status,
        ];
    }
}

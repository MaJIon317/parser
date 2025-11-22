<?php

namespace App\Jobs;

use App\Models\Translation;
use App\Services\OpenAIService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/*
 * Переводим строки из translations на язык по-умолчанию и другие
 */
class TranslationJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public array $ids
    ) { }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $localDefault = config('app.fallback_locale');

        $currentTranslations = [];
        $translations = [];

        foreach ($this->ids as $id) {
            $translation = Translation::find($id);

            if (!$translation) {
                continue; // уже переведено или не найдено
            }

            $source = $translation->source;

            // Проверяем есть ли HTML-теги
            $hasHtml = $source !== strip_tags($source);

            // Отправляем на перевод только если есть HTML или язык отличается
            if ($hasHtml) {
                $translations[$translation->lang][$translation->id] = $source;
            } elseif ($translation->lang === $localDefault) {
                $currentTranslations[$translation->id] = $source;
            } else {
                $translations[$translation->lang][$translation->id] = $source;
            }
        }

        // Переводим, если есть что
        $newTranslations = [];

        foreach ($translations as $lang => $translation) {
            $newTranslations = $newTranslations + $this->translation($lang, $translation);
        }

        $translations = $newTranslations + $currentTranslations;

        foreach ($translations as $id => $translatedText) {
            $target_text = $this->normalizeText($translatedText);

            Translation::where('id', $id)->update([
                'target' => $translatedText,
                'target_text' => $target_text,
                'target_hash' => md5($target_text),
            ]);
        }

        // Сразу добавляем задачу на поиск и обработку дубликатов
        TranslationDuplicatesJob::dispatchSync($this->ids);
    }

    protected function translation(string $lang, array $translations): array
    {
        $defaultLangName = config('app.locales')[config('app.fallback_locale')];
        $langName = config('app.locales')[$lang];

        return (new OpenAIService)->askJson(
            "You're a translator.
    Your task is to translate all the values from {$langName} to {$defaultLangName}.
    If the HTML code is found during the translation, be sure to follow the structure and never make a mistake.
    If you have HTML tags, be sure to process the following tags:
    - headings: h1, h2, h3, h4, h5, h6 - replace with the tag p
    - After converting the header tags, delete the contents of the last p tag if it contains the first character. #
    - links a - delete completely with their contents
    - span and div to delete completely, leaving the contents.
    - remove all attributes from HTML tags in order to get HTML with valid tags at the output: p, strong, br
    - Remove all unnecessary line breaks where there are 2 or more of them or br is located immediately before the closing other tag.
    Keys cannot be changed, deleted, or added.",
            json_encode($translations, JSON_UNESCAPED_UNICODE));
    }

    /**
     * Нормализация текста — учитывает числа и единицы измерения
     */
    protected static function normalizeText(string $text): string
    {
        // 1️⃣ Skip too long texts
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') > 220) {
            return md5($text);
        }

        // 2️⃣ Lowercase
        $text = mb_strtolower($text, 'UTF-8');

        // 3️⃣ Convert units to base units

        // Weight → grams
        $text = preg_replace_callback('/(\d+(?:[\.,]\d+)?)\s?(kg|kilogram|kgs|g|gram|grams)/i', function ($m) {
            $value = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['kg', 'kilogram', 'kgs'])) {
                $value *= 1000;
            }
            return intval(round($value)) . '_g';
        }, $text);

        // Length → millimeters
        $text = preg_replace_callback('/(\d+(?:[\.,]\d+)?)\s?(m|meter|meters|cm|centimeter|centimeters|mm|millimeter|millimeters)/i', function ($m) {
            $value = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['m', 'meter', 'meters'])) {
                $value *= 1000;
            } elseif (in_array($unit, ['cm', 'centimeter', 'centimeters'])) {
                $value *= 10;
            }
            return intval(round($value)) . '_mm';
        }, $text);

        // Volume → milliliters
        $text = preg_replace_callback('/(\d+(?:[\.,]\d+)?)\s?(l|liter|liters|ml|milliliter|milliliters)/i', function ($m) {
            $value = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['l', 'liter', 'liters'])) {
                $value *= 1000;
            }
            return intval(round($value)) . '_ml';
        }, $text);

        // Temperature → Celsius
        $text = preg_replace_callback('/(\d+(?:[\.,]\d+)?)\s?(°c|c|°f|f)/i', function ($m) {
            $value = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['°f', 'f'])) {
                $value = round(($value - 32) * 5 / 9);
            }
            return intval(round($value)) . '_c';
        }, $text);

        // 4️⃣ Remove punctuation except / and _
        $text = str_replace(['.', ',', ';', ':', '!', '?'], ' ', $text);
        $text = preg_replace('/[^\p{L}\p{N}_\/\s]/u', '', $text);
        $text = preg_replace('/\s+/', ' ', trim($text));

        // 5️⃣ Bag-of-words: sort words
        $tokens = preg_split('/[\s\/]+/', $text);
        $tokens = array_filter($tokens);
        sort($tokens, SORT_STRING | SORT_FLAG_CASE);

        return implode(' ', $tokens);
    }
}

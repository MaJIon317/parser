<?php

namespace App\Observers;

use App\Models\Translation;
use Illuminate\Support\Facades\Cache;

class TranslationObserver
{
    public function creating(Translation $translation)
    {
        $translation->hash = md5($translation->source);

        $locale = config('app.locale');
        $translation->from_lang ??= $locale;
        $translation->to_lang ??= $locale;

        // нормализуем и храним текст и его хэш
        $translation->normalized_text = self::normalizeText($translation->target);
        $translation->normalized_hash = md5($translation->normalized_text);
    }

    public function created(Translation $translation): void
    {
        $this->handleDuplicates($translation);
        Cache::put(
            "translate:{$translation->hash}_{$translation->from_lang}_{$translation->to_lang}",
            $translation->target,
            now()->addMonth()
        );
    }

    public function updated(Translation $translation): void
    {
        $translation->normalized_text = self::normalizeText($translation->target);
        $translation->normalized_hash = md5($translation->normalized_text);
        $this->handleDuplicates($translation);

        Cache::put(
            "translate:{$translation->hash}_{$translation->from_lang}_{$translation->to_lang}",
            $translation->target,
            now()->addMonth()
        );
    }

    public function deleted(Translation $translation): void
    {
        $hash = md5($translation->source);
        Cache::forget("translate:{$hash}_{$translation->from_lang}_{$translation->to_lang}");
    }

    /**
     * Основная логика обработки дубликатов
     */
    protected function handleDuplicates(Translation $translation): bool
    {
        $normalized = $translation->normalized_text;

        // ищем точные совпадения
        $duplicates = Translation::where('id', '!=', $translation->id)
            ->where('to_lang', $translation->to_lang)
            ->where('normalized_hash', md5($normalized))
            ->get();

        if ($duplicates->isNotEmpty()) {
            return $this->applyCanonical($translation, $duplicates);
        }

        // ищем похожие по длине/символам (fuzzy)
        $candidates = Translation::where('id', '!=', $translation->id)
            ->where('to_lang', $translation->to_lang)
            ->whereRaw('ABS(CHAR_LENGTH(normalized_text) - ?) < 6', [strlen($normalized)])
            ->limit(200)
            ->get(['id', 'target', 'normalized_text']);

        $fuzzyMatches = $candidates->filter(function ($t) use ($normalized) {
            $numA = $this->extractNumberToken($normalized);
            $numB = $this->extractNumberToken($t->normalized_text);

            // если оба содержат числовые токены — сравниваем строго
            if (!is_null($numA) || !is_null($numB)) {
                if (is_null($numA) || is_null($numB)) {
                    return false;
                }
                return $numA === $numB;
            }

            // обычное fuzzy сравнение
            similar_text($normalized, $t->normalized_text, $percent);
            return $percent >= 90;
        });

        if ($fuzzyMatches->isNotEmpty()) {
            return $this->applyCanonical($translation, $fuzzyMatches);
        }

        return false;
    }

    /**
     * Обновляем canonical_id и кэш
     */
    protected function applyCanonical(Translation $translation, $duplicates): bool
    {
        $canonical = collect([$translation, ...$duplicates])
            ->sortBy(fn($t) => strlen($t->target))
            ->first();

        foreach ($duplicates as $dup) {
            if ($dup->id !== $canonical->id) {
                $dup->updateQuietly(['canonical_id' => $canonical->id]);
            }
        }

        if ($translation->id !== $canonical->id) {
            $translation->updateQuietly(['canonical_id' => $canonical->id]);
        }

        return Cache::put("canonical:{$canonical->normalized_hash}", $canonical->target, now()->addDays(30));
    }

    /**
     * Нормализация текста — учитывает числа и единицы измерения
     */
    protected static function normalizeText(string $text): string
    {
        // 1️⃣ Skip too long texts
        $text = trim($text);
        if (mb_strlen($text, 'UTF-8') > 500) {
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
            return intval(number_format($value)) . '_g';
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
            return intval(number_format($value)) . '_mm';
        }, $text);

        // Volume → milliliters
        $text = preg_replace_callback('/(\d+(?:[\.,]\d+)?)\s?(l|liter|liters|ml|milliliter|milliliters)/i', function ($m) {
            $value = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['l', 'liter', 'liters'])) {
                $value *= 1000;
            }
            return intval(number_format($value)) . '_ml';
        }, $text);

        // Temperature → Celsius
        $text = preg_replace_callback('/(\d+(?:[\.,]\d+)?)\s?(°c|c|°f|f)/i', function ($m) {
            $value = (float) str_replace(',', '.', $m[1]);
            $unit = strtolower($m[2]);
            if (in_array($unit, ['°f', 'f'])) {
                $value = number_format(($value - 32) * 5 / 9);
            }
            return intval(number_format($value)) . '_c';
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

    /**
     * Извлекает числовой токен (например "3000_g" или "500_g")
     */
    protected function extractNumberToken(string $normalizedText): ?string
    {
        if (preg_match('/\b(\d+_g)\b/u', $normalizedText, $m)) {
            return $m[1];
        }
        if (preg_match('/\b(\d+(?:\.\d+)?)\b/u', $normalizedText, $m)) {
            return $m[1];
        }
        return null;
    }
}

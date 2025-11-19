<?php

namespace App\Jobs;

use App\Models\Translation;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;

class TranslationDuplicatesJob implements ShouldQueue
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
        foreach ($this->ids as $id) {
            $translation = Translation::find($id);

            if ($translation) {
                $this->handleDuplicates($translation);
            }
        }
    }

    /**
     * Обновляем canonical_id и кэш
     */
    protected function applyCanonical(Translation $translation, $duplicates): bool
    {
        // Включаем текущую запись и дубликаты
        $all = collect([$translation, ...$duplicates]);

        // Проверяем, есть ли среди них запись без canonical_id — это уже главный
        $existingCanonical = $all->first(fn($t) => is_null($t->canonical_id));

        if ($existingCanonical) {
            $canonical = $existingCanonical;
        } else {
            // Если нет, выбираем первый по id
            $canonical = $all->sortBy('id')->first();
        }

        // Обновляем canonical_id у всех остальных
        foreach ($all as $t) {
            if ($t->id !== $canonical->id) {
                $t->updateQuietly(['canonical_id' => $canonical->id]);
            }
        }

        // Кэшируем главный перевод
        return Cache::put("canonical:{$canonical->target_hash}", $canonical->target, now()->addDays(30));
    }

    /**
     * Основная логика обработки дубликатов
     */
    protected function handleDuplicates(Translation $translation): bool
    {
        $normalized = $translation->target_text;

        // ищем точные совпадения
        $duplicates = Translation::where('id', '!=', $translation->id)
            ->where('lang', $translation->lang)
            ->where('target_hash', md5($normalized))
            ->get();

        if ($duplicates->isNotEmpty()) {
            return $this->applyCanonical($translation, $duplicates);
        }

        // ищем похожие по длине/символам (fuzzy)
        $candidates = Translation::where('id', '!=', $translation->id)
            ->where('lang', $translation->lang)
            ->whereRaw('ABS(CHAR_LENGTH(target_text) - ?) < 6', [strlen($normalized)])
            ->limit(200)
            ->get(['id', 'target', 'target_text']);

        $fuzzyMatches = $candidates->filter(function ($t) use ($normalized) {
            $numsA = $this->extractNumberTokens($normalized);
            $numsB = $this->extractNumberTokens($t->target_text);

            // Если есть числа, они должны полностью совпадать
            if (!empty($numsA) || !empty($numsB)) {
                return $numsA === $numsB;
            }

            // Иначе применяем fuzzy, но с более строгим порогом
            similar_text($normalized, $t->target_text, $percent);
            return $percent >= 95; // увеличиваем порог
        });

        if ($fuzzyMatches->isNotEmpty()) {
            return $this->applyCanonical($translation, $fuzzyMatches);
        }

        return false;
    }

    protected function extractNumberTokens(string $text): array
    {
        // Ищем все числа с единицами и без
        preg_match_all('/\d+/', $text, $matches);
        return $matches[0] ?? [];
    }
}

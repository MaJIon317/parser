<?php

namespace App\Services;

use App\Models\Translation;
use App\Models\TranslationVariant;


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
        $detail = $productData['detail'] ?? [];

        $this->storeAllKeys($detail);

        $flat = $this->flattenValues($detail);
        $translations = $this->translateUsingDatabase($flat);

        return [
            ...$productData,
            'detail' => $this->applyTranslations($detail, $translations),
            'status_translation' => $this->status,
        ];
    }

    protected function translateUsingDatabase(array $flat): array
    {
        $translations = [];

        foreach ($flat as $value) {
            $hash = md5($value);

            $translation = Translation::where('hash', $hash)->first();

            if ($translation && $translation->canonical_id) {
                $canonical = Translation::find($translation->canonical_id);
                if ($canonical) {
                    $translation = $canonical;
                }
            }

            if (!$translation) {
                continue;
            }

            if ($translation->target) {
                $translations[$value] = $translation->target;
                continue;
            }

            $variant = TranslationVariant::where('translation_id', $translation->id)
                ->where('lang', $this->toLang)
                ->first();

            if ($variant) {
                $translations[$value] = $variant->text;
            }
        }

        return $translations;
    }

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

    protected function storeAllKeys(array $data, bool $inAttributes = false): void
    {
        foreach ($data as $key => $value) {
            if ($inAttributes) {
                $this->storeTranslation($key);
            }

            if (is_array($value)) {
                $this->storeAllKeys($value, $inAttributes || $key === 'attributes');
            } else {
                $this->storeTranslation($value);
            }
        }
    }

    protected function storeTranslation(string $source): void
    {
        $hash = md5($source);

        Translation::firstOrCreate(
            [
                'hash' => $hash,
                'lang' => $this->fromLang,
            ],
            [
                'source' => $source,
            ]
        );
    }
}

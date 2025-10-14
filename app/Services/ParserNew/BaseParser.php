<?php

namespace App\Services\ParserNew;

use App\Models\Donor;
use App\Models\Product;
use Illuminate\Support\Facades\Storage;

abstract class BaseParser
{
    /**
     * Базовый URL сайта, с которого производится парсинг.
     */
    protected string $baseUrl;

    /**
     * @param Donor|null $donor Модель донора
     * @param Product|null $product Модель продукта, к которому относится парсер
     * @param HtmlFetcher $fetcher Компонент для получения контента по URL
     * @param bool $test Режим тестирования (без сохранения в БД)
     */
    public function __construct(
        protected ?Donor $donor,
        protected ?Product $product,
        protected HtmlFetcher $fetcher,
        protected bool $test = false,
    ) {}

    /**
     * Основной метод парсинга списка товаров, который должен вернуть данные о товарах.
     *
     * @return iterable
     */
    abstract public function parseList(): iterable;

    /**
     * Основной метод парсинга, который должен вернуть данные о товаре.
     *
     * @return iterable
     */
    abstract public function parseItem(): iterable;

    /**
     * Загрузка изображения по URL и сохранение его в публичное хранилище.
     *
     * @param string $url
     * @return string|null URL сохранённого изображения или null при ошибке
     */
    protected function downloadImage(string $url = ''): ?string
    {
        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';

        $filename = substr(md5($url), 0, 12) . '.' . $ext;

        $path = "{$this->product->uuid}/{$filename}";

        // Проверяем, есть ли файл
        if (Storage::disk('public')->exists($path)) {
            return Storage::url($path);
        }

        // Получаем контент
        $content = $this->fetcher->fetch($url);

        if (empty($content)) {
            return null;
        }

        // Сохраняем файл
        Storage::disk('public')->put($path, $content);

        return Storage::url($path);
    }

    /**
     * Преобразует относительный путь в абсолютный URL.
     *
     * @param string|null $path
     * @return string|null
     */
    protected function makeAbsoluteUrl(?string $path): ?string
    {
        $path = trim($path);

        if (preg_match('/^https?:\/\//i', $path)) {
            return $path;
        }

        if (str_starts_with($path, '//')) {
            $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME) ?? 'https';
            return $scheme . ':' . $path;
        }

        if (str_starts_with($path, '/')) {
            $host = parse_url($this->baseUrl, PHP_URL_HOST);
            $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME) ?? 'https';
            return $scheme . '://' . $host . $path;
        }

        return $this->baseUrl . '/' . ltrim($path, '/');
    }

    /**
     * Сохранение данных о товаре или возврат массива при тестовом режиме.
     *
     * @param array $data
     * @return Product|array|null
     */
    protected function save(array $data): array
    {
        if ($this->test) {
            return $data;
        }

        foreach ($data['images'] ?? [] as $key => $image) {
            $data['images'][$key] = $this->downloadImage($image);
        }

        $this->product->update([
            'detail' => $data['detail'] ?? null,
            'images' => $data['images'] ?? null,
            'parsing_status' => 'parsed_details',
            'last_parsing' => now(),
            'errors' => $data['errors'] ?? null,
        ]);

        return $this->product->toArray();
    }
}

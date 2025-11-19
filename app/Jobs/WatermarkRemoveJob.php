<?php

namespace App\Jobs;

use App\Models\ProductImage;
use App\Models\Webhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Throwable;

/*
 * Удаление вотемарков с изображений у товаров
 */
class WatermarkRemoveJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public ProductImage $productImage,
        public ?Webhook $webhook = null,
    ) {}

    protected function isRemove(Webhook $webhook): bool
    {
        $watermarkRemove = $webhook->setting['watermark']['is_remove'] ?? false;
        $watermarkOlds = $webhook->setting['watermark']['old'] ?? false;
        $watermarkNew = $webhook->setting['watermark']['new'] ?? false;

        if (!$webhook->status || !$watermarkRemove || !$watermarkOlds || !$watermarkNew) return false;

        $category_ids = $webhook->setting['category_ids'] ?? [];

        if ($category_ids && !in_array($this->productImage->product->category_id, $category_ids)) return false;

        return true;
    }

    /**
     * Execute the job.
     * @throws Throwable
     */
    public function handle(): void
    {
        if (!$this->webhook) {
            Webhook::all()->each(function (Webhook $webhook) {
                $this->resetWatermark($webhook);
            });
        } else {
            $this->resetWatermark($this->webhook);
        }
    }

    /**
     * @throws Throwable
     * @throws ConnectionException
     */
    protected function resetWatermark(Webhook $webhook): void
    {
        if (!$this->isRemove($webhook)) return;

            $storage = $this->productImage->storage();

            $watermarks = [];

            foreach ($webhook->setting['watermark']['old'] as $old) {
                $watermarks[] = $storage->url($old);
            }

            // Входные параметры
            $payload = [
                'image' => $storage->url($this->productImage->url),
                'wotemark' => implode(',', $watermarks),
                'to-wotemark' => $storage->url($webhook->setting['watermark']['new']),
            ];

            // Ключ в кэше
            $hashed = md5(json_encode($payload));

            // Если такой запрос уже делали — пропускаем
            if (($this->productImage->hashed[$webhook->id] ?? null) === $hashed) {
                return;
            }

        try {

            // Отправляем POST-запрос к Flask API
            $response = Http::asForm()->post(config('services.watermark_remove.api_url'), $payload);

            if (!$response->successful()) {
                throw new \Exception("WatermarkRemoveJob: Ошибка API: " . $response->body());
            }

            // Сохраняем пути (основной путь, донор, наименование файла (такое же)
            $fileInfo = pathinfo($this->productImage->url);

            $path = "{$fileInfo['dirname']}/ready/{$webhook->id}/{$fileInfo['basename']}";

            $storage->put($path, $response->body());

            $correct_url = $this->productImage->correct_url ?? [];

            $correct_url[$webhook->id] = $path;

            $hashedes = $this->productImage->hashed ?? [];

            $hashedes[$webhook->id] = $hashed;

            // Обновляем запись модели
            $this->productImage->updateQuietly([
                'correct_url' => $correct_url,
                'hashed' => $hashedes, // Чтобы не делать постоянно запросы к сервису, мы контролируем обработанные изображения по хэшу запроса
            ]);

        } catch (\Throwable $e) {
            // Логируем ошибку
            \Log::error("WatermarkRemoveJob: Ошибка при обработке изображения для вебхука ($webhook->name)" . $e->getMessage(), [
                'product_image_id' => $this->productImage->id,
                'webhook_id' => $webhook->id
            ]);

            throw $e;
        }
    }
}

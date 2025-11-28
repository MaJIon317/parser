<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Webhook;
use App\Services\WebhookService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class WebhookCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected string $requestId;

    public function __construct(
        public Product $product,
        public bool $isRetry = false,
        public string $type_processing = 'update',
    ) {
        $this->requestId = Str::uuid();
    }

    /**
     * @throws Exception
     */
    public function handle(WebhookService $webhookService): void
    {
        $webhooks = Webhook::where('status', true)->get();

        foreach ($webhooks as $webhook) {
            if (!$this->isParsed($webhook, $this->product)) {

                $this->logFailure($webhook->id, "The product cannot be unloaded due to the settings", $webhook->setting);
                continue;
            }

            $payload = $this->preparePayload($webhook, $webhook->setting);

            if (!($payload['status_translation'] ?? false)) {
                $this->logFailure($webhook->id, "Couldn't send webhook: translation is unavailable on {$webhook->locale}", $payload);
                continue;
            }

            try {
                $webhookService->sendWebhook($webhook, $payload);
                $this->logSuccess($webhook->id, $payload);
            } catch (Exception $e) {
                // Логируем ошибку только при первой попытке
                if (!$this->isRetry) {
                    $this->logFailure($webhook->id, $e->getMessage(), $payload);
                }

                throw $e; // чтобы Laravel повторил Job
            }
        }
    }

    protected function preparePayload(Webhook $webhook, array $setting = []): array
    {
        $webhookId = $webhook->id;

        // Если у проекта нет настроек изображений
        $images = [];

        if ($webhook->setting['watermark']['is_remove'] ?? false) {
            foreach ($this->product->images ?? [] as $image) {
                WatermarkRemoveJob::dispatchSync($image);
            }

            foreach ($this->product->images as $image) {
                $path = $image->correct_url[$webhookId] ?? null;

                if ($path) {
                    $images[] = $path;
                }
            }
        } else {
            foreach ($this->product->images as $image) {
                $images[] = $image->url;
            }
        }

        $currency_code = $webhook->currency->code;

        $price_adjustment = $setting['price_adjustment_category'][$this->product->category_id]['price']
            ?? $setting['price_adjustment']
            ?? 0;

        $price = $this->product->price * (1 + ($price_adjustment / 100));

        return Arr::except([
            ...$this->product->isTranslation($webhook->locale ?? config('app.locale')),
            'images'   => $images,
            'uuid'     => $this->product->uuid,
            'code'     => $this->product->code,
            'object'   => $this->product->category?->name,
            'type_processing' => $this->type_processing,
            'price' => $this->product->priceConvert($currency_code, $price),
            'currency' => $currency_code,
        ], [
            'id',
            'parsing_status',
            'donor_id',
            'category_id',
            'currency_id',
            'donor',
            'errors',
            'created_at',
        ]);
    }

    protected function logSuccess(int $webhookId, array $payload): void
    {
        $this->product->logs()->create([
            'request_id' => $this->requestId,
            'model_type' => Webhook::class,
            'model_id'   => $webhookId,
            'type' => 'success',
            'code' => 'webhook',
            'message' => 'The webhook was successfully sent',
            'data' => $payload,
        ]);
    }

    protected function logFailure(int $webhookId, string $message, array $payload): void
    {
        $this->product->logs()->create([
            'request_id' => $this->requestId,
            'model_type' => Webhook::class,
            'model_id' => $webhookId,
            'type' => 'error',
            'code' => 'webhook',
            'message' => $message,
            'data' => $payload,
        ]);
    }

    /*
     * Проверяем должен ли выгружаться товар
     */
    protected function isParsed(Webhook $webhook, Product $product): bool
    {
        $status = true;

        $category_ids = $webhook->setting['category_ids'] ?? [];

        if ($category_ids && !in_array($product->category_id, $category_ids)) {
            $status = false;
        }

        return $status;
    }
}

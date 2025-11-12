<?php

namespace App\Jobs;

use App\Models\Product;
use App\Models\Webhook;
use App\Models\WebhookLog;
use App\Services\CurrencyService;
use App\Services\WebhookService;
use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Arr;

class WebhookCallJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public Product $product,
        public bool $isRetry = false,
        public string $type_processing = 'update',
    ) {}

    /**
     * @throws Exception
     */
    public function handle(WebhookService $webhookService, CurrencyService $currencyService): void
    {
        $webhooks = Webhook::where('status', true)->get();

        foreach ($webhooks as $webhook) {
            $payload = $this->preparePayload($webhook);

            if (!($payload['status_translation'] ?? false)) {
                $this->logFailure($webhook->id, "Не удалось отправить вебхук: перевод недоступен", $payload);
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

    protected function preparePayload(Webhook $webhook): array
    {
        $currency_code = $webhook->currency->code;

        return Arr::except([
            ...$this->product->isTranslation($webhook->locale ?? config('app.locale')),
            'images'   => $this->product->images,
            'uuid'     => $this->product->uuid,
            'code'     => $this->product->code,
            'object'   => $this->product->category?->name,
            'type_processing' => $this->type_processing,
            'price' => $this->product->priceConvert($currency_code),
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
        WebhookLog::create([
            'webhook_id' => $webhookId,
            'product_id' => $this->product->id,
            'message'    => 'Вебхук успешно отправлен',
            'data'       => $payload,
        ]);
    }

    protected function logFailure(int $webhookId, string $message, array $payload): void
    {
        WebhookLog::create([
            'webhook_id' => $webhookId,
            'product_id' => $this->product->id,
            'message'    => $message,
            'data'       => $payload,
        ]);
    }
}

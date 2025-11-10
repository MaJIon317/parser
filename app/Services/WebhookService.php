<?php

namespace App\Services;

use App\Models\Webhook;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Exception;

class WebhookService
{
    /**
     * Отправка данных вебхука (POST).
     *
     * @param Webhook $webhook
     * @param array $payload
     * @return void
     * @throws Exception
     */
    public function sendWebhook(Webhook $webhook, array $payload): void
    {
        $this->request($webhook, 'POST', $payload);
    }

    /**
     * Пинг вебхука (GET) с подписью.
     *
     * @param Webhook $webhook
     * @return bool
     */
    public function ping(Webhook $webhook): bool
    {
        try {
            $webhook->url = $webhook->url . '/ping';

            $response = $this->request($webhook, 'GET');

            return $response->successful();
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Универсальный метод отправки HTTP запроса с подписью HMAC SHA256.
     *
     * @param Webhook $webhook
     * @param string $method
     * @param array|null $payload
     * @return Response
     * @throws Exception
     */
    protected function request(Webhook $webhook, string $method, ?array $payload = null): Response
    {
        $payloadJson = $payload ? json_encode($payload) : '';

        // Вычисляем подпись
        $signature = hash_hmac('sha256', $payloadJson, $webhook->secret);

        $headers = [
            'Content-Type' => 'application/json',
            'Signature'    => $signature,
        ];

        // Выбираем метод запроса
        $http = match(strtoupper($method)) {
            'POST' => Http::withHeaders($headers)->post($webhook->url, $payload),
            'GET'  => Http::withHeaders($headers)->get($webhook->url),
            'PUT'  => Http::withHeaders($headers)->put($webhook->url, $payload),
            'PATCH'=> Http::withHeaders($headers)->patch($webhook->url, $payload),
            'DELETE'=> Http::withHeaders($headers)->delete($webhook->url, $payload),
            default => throw new Exception("Неизвестный HTTP метод: {$method}"),
        };

        if (!$http->successful()) {
            throw new Exception("Ошибка при отправке вебхука '{$webhook->name}', HTTP {$http->status()}");
        }

        return $http;
    }
}

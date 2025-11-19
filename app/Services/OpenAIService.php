<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use OpenAI\Laravel\Facades\OpenAI;

class OpenAIService
{
    /**
     * Универсальный метод для безопасного JSON-ответа от ChatGPT
     */
    public function askJson(string $systemPayload, string $userPayload, string $model = 'gpt-4.1-mini', int $ttlSeconds = 3600): array
    {
        // Генерируем ключ кэша по md5 от system + user payload
        $cacheKey = 'openai_json_' . md5($systemPayload . '|' . $userPayload);

        // Если есть в кэше — сразу возвращаем
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        // Делаем запрос к ChatGPT
        $response = OpenAI::chat()->create([
            'model' => $model,
            'response_format' => [
                'type' => 'json_object'
            ],
            'messages' => [
                [
                    'role' => 'system',
                    'content' => "{$systemPayload}. Make sure to return only valid JSON without text outside of JSON.",
                ],
                [
                    'role' => 'user',
                    'content' => $userPayload,
                ]
            ]
        ]);

        $result = json_decode($response->choices[0]->message->content, true);

        // Сохраняем в кэш только если JSON валиден
        if (is_array($result)) {
            Cache::put($cacheKey, $result, $ttlSeconds);
        }

        return $result ?? [];
    }
}

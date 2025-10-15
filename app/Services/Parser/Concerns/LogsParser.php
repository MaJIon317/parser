<?php

namespace App\Services\Parser\Concerns;

use App\Models\Logging;

trait LogsParser
{
    protected ?Logging $currentLog = null;
    protected ?float $logStartTime = null;

    protected function startLog(string $type, array $extra = []): void
    {
        $this->logStartTime = microtime(true);

        $this->currentLog = Logging::create(array_merge([
            'type' => $type,
            'donor_id' => $this->donor->id ?? null,
            'product_id' => $this->product->id ?? null,
            'url' => $this->product->url ?? null,
            'parser_class' => static::class,
            'status' => 'running',
            'started_at' => now(),
        ], $extra));
    }

    protected function finishLog(string $status = 'success', string $message = null, array $context = []): void
    {
        if (!$this->currentLog) {
            return;
        }

        $this->currentLog->update([
            'status' => $status,
            'message' => $message,
            'context' => $context,
            'finished_at' => now(),
            'duration_ms' => (int) ((microtime(true) - $this->logStartTime) * 1000),
        ]);
    }

    protected function failLog(string $message, array $context = []): void
    {
        $this->finishLog('error', $message, $context);
    }

    protected function log(string $status, string $message, array $context = []): void
    {
        Logging::create([
            'type' => 'manual',
            'donor_id' => $this->donor->id ?? null,
            'product_id' => $this->product->id ?? null,
            'url' => $this->product->url ?? null,
            'parser_class' => static::class,
            'status' => $status,
            'message' => $message,
            'context' => $context,
        ]);
    }
}

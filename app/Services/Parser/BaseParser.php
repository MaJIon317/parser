<?php

namespace App\Services\Parser;

use App\Models\Donor;

abstract class BaseParser
{
    protected Donor $donor;
    protected ?string $proxy = null;

    public function __construct(Donor $donor, string $proxy = null)
    {
        $this->donor = $donor;
        $this->proxy = $proxy;
    }

    public function getRateLimit(): int
    {
        return $this->donor->rate_limit;
    }

    public function getDelay(): array
    {
        return [$this->donor->delay_min, $this->donor->delay_max];
    }

    public function getRefreshInterval(): int
    {
        return $this->donor->refresh_interval;
    }

    public function getUserAgent(): ?string
    {
        return $this->donor->user_agent;
    }
}

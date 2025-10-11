<?php

namespace App\Services\Parser;

use App\Models\Proxy;

class ProxyManager
{
    public static function getRandomActive(): ?string
    {
        $proxy = Proxy::where('is_active', true)->inRandomOrder()->first();
        if (!$proxy) return null;

        $auth = $proxy->username ? "{$proxy->username}:{$proxy->password}@" : '';
        return "{$proxy->scheme}://{$auth}{$proxy->host}:{$proxy->port}";
    }
}

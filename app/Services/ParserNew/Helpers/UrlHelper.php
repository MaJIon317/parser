<?php

namespace App\Services\ParserNew\Helpers;

use App\Models\Donor;

class UrlHelper
{
    protected string $baseUrl;

    public function __construct(
        protected Donor $donor
    ) {
        $this->baseUrl = rtrim($donor->base_url, '/');
    }

    public function normalize(string $url): string
    {
        $url = trim($url);
        if (preg_match('/^https?:\/\//i', $url)) {
            return $url;
        }

        if (str_starts_with($url, '//')) {
            $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME) ?? 'https';
            return $scheme . ':' . $url;
        }

        if (str_starts_with($url, '/')) {
            $host = parse_url($this->baseUrl, PHP_URL_HOST);
            $scheme = parse_url($this->baseUrl, PHP_URL_SCHEME) ?? 'https';
            return $scheme . '://' . $host . $url;
        }

        return $this->baseUrl . '/' . ltrim($url, '/');
    }
}

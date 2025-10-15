<?php

namespace App\Services\Parser;

use Illuminate\Support\Facades\Storage;

class ImageDownloader
{
    public function download(string $url, string $uuid): ?string
    {
        if (!$url) return null;

        $ext = pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION) ?: 'jpg';
        $filename = substr(md5($url), 0, 12) . '.' . $ext;
        $path = "{$uuid}/{$filename}";

        if (Storage::exists($path)) {
            return Storage::url($path);
        }

        $content = file_get_contents($url);
        if (!$content) return null;

        Storage::put($path, $content);

        return Storage::url($path);
    }
}

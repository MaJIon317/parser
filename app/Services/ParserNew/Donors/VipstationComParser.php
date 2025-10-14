<?php

namespace App\Services\ParserNew\Donors;

use App\Services\ParserNew\BaseParser;
use App\Services\ParserNew\DomParser;
use Exception;

class VipstationComParser extends BaseParser
{
    protected string $baseUrl = 'https://www.vipstation.com.hk/';

    public function parseList(): iterable
    {

        return [];
    }

    public function parseItem(): iterable
    {
        $url = $this->product->url;

        $url = 'https://www.vipstation.com.hk/en/item/audemars-piguet-15510stoo1320st01-watches.html';

        // Получаем HTML страницы
        $html = $this->fetcher->fetch($url);

        if (!$html) {
            return $this->save([
                'errors' => ['error' => 'Не удалось загрузить страницу'],
            ]);
        }

        $dom = new DomParser($html);

        // 1️⃣ Получаем содержимое скрипта с данными
        $scriptContent = $dom->query('//script[contains(text(), "var iteminfo")]')?->item(0)?->textContent;

        if (!$scriptContent) {
            throw new Exception('Не удалось найти скрипт с данными товара');
        }

        // Извлекаем JSON-подобные данные через регулярки
        preg_match('/var\s+iteminfo\s*=\s*(\{.*?\});/s', $scriptContent, $iteminfoMatches);
        preg_match('/var\s+imgList\s*=\s*(\[[^\]]*\]);/s', $scriptContent, $imgListMatches);
        preg_match('/var\s+price\s*=\s*"([^"]+)"/', $scriptContent, $priceMatches);
        preg_match('/var\s+recommend\s*=\s*(\[[^\]]*\]);/s', $scriptContent, $recommendMatches);

        $itemInfoJson = $iteminfoMatches[1] ?? '{}';
        $imgListJson = $imgListMatches[1] ?? '[]';

        $itemInfo = json_decode($itemInfoJson, true);
        $images = json_decode($imgListJson, true);

        return $this->save(array_filter([
            'detail' => array_filter([
                'name' => $itemInfo['ST_NAME'] ?? null,
                'category' => implode('/', array_filter([$itemInfo['ST_WEB_CATALOG'] ?? null, $itemInfo['ST_WEB_SUBCATALOG'] ?? null])),
                'description' => $itemInfo['ST_ADVERTISING'] ?? null,
                'sku' => $itemInfo['ST_CODE'] ?? null,
                'attributes' => !empty($itemInfo['ST_PRODETAILS']) ? array_column($itemInfo['ST_PRODETAILS'], 'ST_VALUE', 'ST_KEY') : [],
            ]),
            'images' => $images ?? [],
        ]));
    }

}

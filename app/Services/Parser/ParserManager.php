<?php

namespace App\Services\Parser;

use App\Models\Donor;
use InvalidArgumentException;

class ParserManager
{
    public static function make(string $donorName, string $proxy = null): BaseParser
    {
        $donor = Donor::where('name', $donorName)
            ->where('is_active', true)
            ->firstOrFail();

        $class = self::getParserClass($donorName);

        if (!class_exists($class)) {
            throw new InvalidArgumentException("Parser for donor {$donorName} not found.");
        }

        return new $class($donor, $proxy);
    }

    protected static function getParserClass(string $donor): string
    {
        $map = [
            'watchnian.com' => \App\Services\Parser\Donors\WatchnianParser::class,
        ];

        return $map[$donor] ?? throw new InvalidArgumentException("Parser not defined for {$donor}");
    }
}

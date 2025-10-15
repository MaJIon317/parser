<?php

namespace App\Filament\Resources\Loggings\Pages;

use App\Filament\Resources\Loggings\LoggingResource;
use Filament\Resources\Pages\ListRecords;

class ListLoggings extends ListRecords
{
    protected static string $resource = LoggingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}

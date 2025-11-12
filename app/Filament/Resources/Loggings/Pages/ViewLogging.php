<?php

namespace App\Filament\Resources\Loggings\Pages;

use App\Filament\Resources\Loggings\LoggingResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewLogging extends ViewRecord
{
    protected static string $resource = LoggingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}

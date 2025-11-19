<?php

namespace App\Filament\Resources\ProductImages\Pages;

use App\Filament\Resources\ProductImages\ProductImageResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

class ManageProductImages extends ManageRecords
{
    protected static string $resource = ProductImageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

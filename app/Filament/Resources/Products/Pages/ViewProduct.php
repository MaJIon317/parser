<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\ProductParserJob;
use App\Models\Product;
use App\Services\Parser\ParserService;
use App\Services\Parser\ProductParser;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('parse_test')
                ->color('warning')
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Product $record) {

                    (new ProductParser($record))->parse(test: true);

                }),

            Action::make('customAction')
                ->label(__('Parse'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Product $record) {

                    ProductParserJob::dispatchSync($record);

                    $record->refresh();
                }),

        ];
    }
}

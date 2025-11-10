<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\ProductResource;
use App\Jobs\ParseProductJob;
use App\Jobs\WebhookCallJob;
use App\Models\Product;
use App\Services\Parser;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewProduct extends ViewRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [

            Action::make('customAction')
                ->label(__('Parse'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Product $record) {

                    ParseProductJob::dispatchSync($record);

                    $record->refresh();

                    Notification::make()
                        ->title(__('The product has been updated'))
                        ->success()
                        ->send();
                }),

            Action::make('customAction')
                ->label(__('WebhookCallJob'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Product $record) {

                    WebhookCallJob::dispatchSync($record);

                    Notification::make()
                        ->title(__('The product has been updated'))
                        ->success()
                        ->send();
                }),

        ];
    }
}

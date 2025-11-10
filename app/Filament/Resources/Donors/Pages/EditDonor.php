<?php

namespace App\Filament\Resources\Donors\Pages;

use App\Filament\Resources\Donors\DonorResource;
use App\Jobs\ParseDonorJob;
use App\Jobs\ParseProductJob;
use App\Models\Donor;
use App\Services\Parser;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditDonor extends EditRecord
{
    protected static string $resource = DonorResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('parse_test')
                ->color('warning')
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Donor $record) {

                    $test = Parser::make($record)->pages();

                    dd($test);
                }),

            ActionGroup::make([

                    Action::make('parse') // Парсим страницы каталога донора
                        ->label(__('Parse'))
                        ->tooltip('Parses all the donor\'s pages.')
                        ->action(function (Donor $record) {

                            $record->products()->update([
                                'parsing_status' => 'new'
                            ]);

                            ParseDonorJob::dispatchSync($record);

                            Notification::make()
                                ->title(__('Sent for processing'))
                                ->body(__('Please wait for the notification.'))
                                ->success()
                                ->send();
                        }),

                    Action::make('customActionAllProduct') // Обновляем все товары
                        ->label(__('Product parsing'))
                        ->tooltip('Sends all the donor\'s products to the download queue.')
                        ->action(function (Donor $record) {

                            $record->products()
                                ->select('id') // минимум полей
                                ->chunk(300, function ($products) {
                                    foreach ($products as $product) {
                                        ParseProductJob::dispatch($product);
                                    }
                                });

                            Notification::make()
                                ->title(__('Sent for processing'))
                                ->body(__('Please wait for the notification.'))
                                ->success()
                                ->send();
                        }),

                    Action::make('customActionAllPriceProduct') // Обновляем только цены
                        ->label(__('Update prices for all products'))
                            ->tooltip('Sends all the donor\'s products to the queue for the price update.')
                            ->action(function (Donor $record) {

                                $record->products()->update([
                                    'parsing_status' => 'price'
                                ]);

                                $record->products()
                                    ->select('id') // минимум полей
                                    ->chunk(300, function ($products) {
                                        foreach ($products as $product) {
                                            ParseProductJob::dispatch($product);
                                        }
                                    });

                                Notification::make()
                                    ->title(__('Sent for processing'))
                                    ->body(__('Please wait for the notification.'))
                                    ->success()
                                    ->send();
                            }),

                ])
        ];
    }
}

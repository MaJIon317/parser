<?php

namespace App\Filament\Resources\Donors\Pages;

use App\Filament\Resources\Donors\DonorResource;
use App\Jobs\ParseDonorJob;
use App\Jobs\ParseProductJob;
use App\Models\Donor;
use App\Services\Parser;
use Filament\Actions\Action;
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

            Action::make('customAction')
                ->label(__('Parse'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
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

            Action::make('customActionAllProduct')
                ->label(__('Parse App Product'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Donor $record) {

                    foreach ($record->products as $product) {
                        ParseProductJob::dispatch($product);
                    }

                    Notification::make()
                        ->title(__('Sent for processing'))
                        ->body(__('Please wait for the notification.'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

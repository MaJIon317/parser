<?php

namespace App\Filament\Resources\Donors\Pages;

use App\Filament\Resources\Donors\DonorResource;
use App\Jobs\PagesParserJob;
use App\Models\Donor;
use App\Services\Parser\PageParser;
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

                    (new PageParser($record))->parse(test: true);

                }),

            Action::make('customAction')
                ->label(__('Parse'))
                ->icon('heroicon-o-inbox-arrow-down')
                ->tooltip('The product data will be downloaded from the donor\'s website.')
                ->action(function (Donor $record) {

                    PagesParserJob::dispatch($record);

                    $record->refresh();

                    Notification::make()
                        ->title(__('Sent for processing'))
                        ->body(__('Please wait for the notification.'))
                        ->success()
                        ->send();
                }),
        ];
    }
}

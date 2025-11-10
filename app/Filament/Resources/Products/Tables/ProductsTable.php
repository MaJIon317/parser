<?php

namespace App\Filament\Resources\Products\Tables;

use App\Jobs\ParseProductJob;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('donor.name')
                    ->label(__('Donor'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('category.name')
                    ->label(__('Category'))
                    ->searchable()
                    ->sortable()
                    ->wrap(),
                TextColumn::make('code')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('formatted_price')
                    ->sortable(),
                TextColumn::make('status')
                    ->sortable(),
                TextColumn::make('parsing_status')
                    ->sortable(),
                TextColumn::make('url')
                    ->formatStateUsing(fn ($state) => 'LINK')
                    ->url(fn ($state) => $state)
                    ->openUrlInNewTab()
                    ->searchable(),
                TextColumn::make('images')
                    ->state(fn($record) => count($record->images ?? [])),
                TextColumn::make('detail.attributes')
                    ->label('Attributes')
                    ->state(fn($record) => count($record->detail['attributes'] ?? []))
                    ->searchable(),
                TextColumn::make('last_parsing')
                    ->dateTime(),
                TextColumn::make('errors')
                    ->words(10)
                    ->wrap(),
            ])
            ->filters([
                Filter::make('is_images')
                    ->label(__('With images'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('images')
                                                    ->orWhere('images', '!=', '{}')),

                Filter::make('errors')
                    ->label(__('With errors'))
                    ->toggle()
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('errors')
                        ->orWhere('errors', '!=', '{}')),

                SelectFilter::make('status')
                    ->options(function () {
                        $statuses = [];

                        foreach (Product::select('status')->distinct()->pluck('status') as $status) {
                            $statuses[$status] = str($status)->headline();
                        }

                        return $statuses;
                    }),

                SelectFilter::make('parsing_status')
                    ->options(function () {
                        $statuses = [];

                        foreach (Product::select('parsing_status')->distinct()->pluck('parsing_status') as $status) {
                            $statuses[$status] = str($status)->headline();
                        }

                        return $statuses;
                    }),
            ])
            ->deferFilters(false)
            ->recordActions([

                Action::make('customAction')
                    ->label(__('Parse'))
                    ->icon('heroicon-o-inbox-arrow-down')
                    ->tooltip('The product data will be downloaded from the donor\'s website.')
                    ->action(function (Product $record) {

                        ParseProductJob::dispatchSync($record);

                        $record->refresh();
                    }),

                ViewAction::make()
                    ->label(''),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('parse')
                        ->action(function (Collection $records) {
                            foreach ($records as $record) {
                                ParseProductJob::dispatch($record);
                            }

                            Notification::make()
                                ->title(__('Sent for processing'))
                                ->body(__('Please wait for the notification.'))
                                ->success()
                                ->send();
                        })
                        ->closeModalByClickingAway()
                        ->deselectRecordsAfterCompletion(),

                    DeleteBulkAction::make(),
                ]),
            ])
            ->poll('20s');
    }
}

<?php

namespace App\Filament\Resources\ProductImages;

use App\Filament\Resources\ProductImages\Pages\ManageProductImages;
use App\Jobs\WatermarkRemoveJob;
use App\Models\Donor;
use App\Models\Product;
use App\Models\ProductImage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductImageResource extends Resource
{
    protected static ?string $model = ProductImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function table(Table $table): Table
    {
        return $table
            ->searchable(['product_id'])
            ->columns([
                Split::make([

                    ImageColumn::make('url')
                        ->imageHeight(60)
                        ->square()
                        ->url(fn ($record) => $record->url ? $record->storage()->url($record->url) : '')
                        ->openUrlInNewTab()
                        ->extraAttributes([
                            'lazy' => 'loading',
                        ])
                        ->columnSpan('fill'),

                    ImageColumn::make('correct_url')
                        ->imageHeight(60)
                        ->square()
                        ->circular()
                        ->stacked()
                        ->extraAttributes([
                            'lazy' => 'loading',
                        ])
                        ->columnSpan('fill'),
                ])
            ])
            ->defaultGroup('product_id')
            ->contentGrid([
                'md' => 3,
                'lg' => 5,
                '2xl' => 7,
            ])
            ->filters([
                SelectFilter::make('donor')
                    ->label('Donor')
                    ->options(
                        Donor::pluck('name', 'id')
                    )
                    ->query(function (Builder $query, $state) {
                        $value = $state['value'] ?? null;

                        if (! $value) {
                            // ❗ Если фильтр НЕ выбран — не менять запрос!
                            return $query;
                        }

                        return $query->whereHas('product', function (Builder $query) use ($value) {
                            $query->where('donor_id', $value);
                        });
                    })
            ])
            ->paginated([20])
            ->defaultPaginationPageOption(20)
            ->deferFilters(false)
            ->recordActions([
                Action::make('Replace watermark')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('warning')
                    ->action(function (ProductImage $record) {
                        $record->updateQuietly([
                            'correct_url' => null,
                            'hashed' => null,
                        ]);

                        WatermarkRemoveJob::dispatchSync($record);

                        Notification::make()
                            ->title(__('Successfully sent'))
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    // DeleteBulkAction::make(),

                    BulkAction::make('Replace watermark')
                        ->icon('heroicon-o-rocket-launch')
                        ->action(function ($records) {

                            $records->chunk(500)->each(function ($records) {
                                foreach ($records as $record) {
                                    WatermarkRemoveJob::dispatch($record);
                                }
                            });

                            Notification::make()
                                ->title(__('Successfully sent'))
                                ->success()
                                ->send();
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProductImages::route('/'),
        ];
    }
}

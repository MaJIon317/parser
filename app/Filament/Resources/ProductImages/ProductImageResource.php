<?php

namespace App\Filament\Resources\ProductImages;

use App\Filament\Resources\ProductImages\Pages\ManageProductImages;
use App\Jobs\WatermarkRemoveJob;
use App\Models\ProductImage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\Layout\Split;
use Filament\Tables\Columns\Layout\Stack;
use Filament\Tables\Table;

class ProductImageResource extends Resource
{
    protected static ?string $model = ProductImage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Split::make([

                    ImageColumn::make('url')
                        ->imageHeight(60)
                        ->square()
                        ->url(fn ($record) => $record->url ? $record->storage()->url($record->url) : '')
                        ->openUrlInNewTab()
                        ->columnSpan('fill'),

                    ImageColumn::make('correct_url')
                        ->imageHeight(60)
                        ->square()
                        ->circular()
                        ->stacked()
                        ->columnSpan('fill'),
                ])
            ])
            ->defaultGroup('product.id')
            ->contentGrid([
                'md' => 3,
                'lg' => 5,
                '2xl' => 7,
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('Replace watermark')
                    ->icon('heroicon-o-rocket-launch')
                    ->color('warning')
                    ->action(function (ProductImage $record) {
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

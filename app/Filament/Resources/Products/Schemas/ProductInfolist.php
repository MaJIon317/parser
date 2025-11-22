<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Jobs\WatermarkRemoveJob;
use App\Models\Product;
use App\Models\Webhook;
use App\Services\ProductWithdrawalService;
use Filament\Actions\Action;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([

            TextEntry::make('errors')
                ->color('danger')
                ->columnSpanFull()
                ->visible(fn ($record) => !empty($record['errors'])),

            Section::make('Images')
                ->schema(fn($record) => static::imageReady($record))
                ->columnSpanFull()
                ->visible(fn ($record) => $record->images()->count())
                ->belowContent([
                    Action::make('Replace watermark')
                        ->icon('heroicon-o-rocket-launch')
                        ->color('warning')
                        ->action(function ($record) {
                            foreach ($record->images as $image) {
                                WatermarkRemoveJob::dispatchSync($image);
                            }

                            $record->refresh();
                        })
                ]),

            Section::make('Основная информация')
                ->columns(2)
                ->schema([

                    TextEntry::make('url')
                        ->url(fn ($record) => $record['url'])
                        ->openUrlInNewTab()
                        ->copyable()
                        ->columnSpanFull(),

                    TextEntry::make('category.name')
                        ->label(__('Category'))
                        ->weight('medium')
                        ->color('warning')
                        ->size('lg'),

                    TextEntry::make('formatted_price')
                        ->weight('medium')
                        ->color('info'),

                    TextEntry::make('status')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'active' => 'success',
                            'wait' => 'warning',
                            'error' => 'danger',
                            default => 'gray',
                        }),

                    TextEntry::make('parsing_status')
                        ->badge()
                        ->color(fn ($state) => match ($state) {
                            'done' => 'success',
                            'wait' => 'warning',
                            default => 'gray',
                        }),
                ])
                ->columnSpanFull()
                ->collapsible(),

            Tabs::make('Detail')
                ->tabs(
                    collect(array_merge([config('app.locale') => ucfirst(config('app.locale'))], config('app.locales')))->map(function ($label, $locale) {

                        return Tab::make($label)
                            ->schema([
                                KeyValueEntry::make("detail_{$locale}") // можно хранить переводы в разных полях
                                ->label('')
                                    ->state(function (Product $record) use ($locale) {
                                        $toArray = $record->getAttributes();
                                        $toArray['detail'] = json_decode($toArray['detail'], true);

                                        $toArray = (new ProductWithdrawalService)->getTranslatedProduct($toArray);

                                        return $record->detail ? static::detailArray($toArray) : [];
                                    }),
                            ]);
                    })->toArray()
                )
                ->columnSpanFull(),

        ]);
    }

    protected static function detailArray(?array $array = null): array
    {
        return Arr::dot($array ?? []) ?? [];
    }

    protected static function imageReady(Product $product): array
    {
        $result = [];

        $images = $product->images;

        $collectionImages = [
            'default' => $images->pluck('url')->toArray(),

            'donor' => $images
                ->flatMap(function ($img) {
                    return collect($img->correct_url ?? [])
                        ->map(fn ($path, $key) => ['donor_id' => $key, 'path' => $path]);
                })
                ->groupBy('donor_id')
                ->map(fn ($group) => $group->pluck('path')->toArray())
                ->toArray(),
        ];

        foreach ($collectionImages as $collectionImageKey => $collectionImage) {

            if ($collectionImageKey == 'default') {
                $result[] = ImageEntry::make($collectionImageKey)
                    ->state(function () use ($collectionImage) {
                        return collect($collectionImage)
                            ->map(fn($path) => Storage::url($path))
                            ->toArray();
                    })
                    ->columnSpanFull()
                    ->imageWidth(80)
                    ->imageHeight(80);
            } else {
                foreach ($collectionImage as $key => $values) {
                    $result[] = ImageEntry::make($key)
                        ->label(implode(': ', ['Webhook', Webhook::find($key)?->name ?? $key]))
                        ->state(function () use ($values) {
                            return collect($values)
                                ->map(fn($path) => Storage::url($path))
                                ->toArray();
                        })
                        ->columnSpanFull()
                        ->imageWidth(80)
                        ->imageHeight(80);
                }

            }
        }

        return $result;
    }
}

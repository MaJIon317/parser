<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Components\Grid;
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

            ImageEntry::make('images')
                ->state(function ($record) {
                    return collect($record->images ?? [])
                        ->map(fn ($path) => Storage::url($path))
                        ->toArray();
                })
                ->columnSpanFull()
                ->imageWidth(80)
                ->imageHeight(80)
                ->visible(fn ($record) => !empty($record['images'])),

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
                ->tabs([
                    Tab::make('Default')
                        ->schema([

                                KeyValueEntry::make('detail')
                                    ->label('')
                                    ->state(function ($record) {
                                        return static::detailArray($record->detail);
                                    }),
                        ])
                ])
                ->columnSpanFull(),

        ]);
    }

    protected static function detailArray(?array $array = null): array
    {
        return Arr::dot($array ?? []) ?? [];
    }
}

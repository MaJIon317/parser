<?php

namespace App\Filament\Resources\Products\Schemas;

use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\KeyValueEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\TextEntry;

class ProductInfolist
{
    public static function configure(Schema $schema): Schema
    {
        $defaultLocale = config('app.fallback_locale');
        $locale = $schema->getRecord()->donor->setting['language'] ?? $defaultLocale;

        return $schema->components([

            ImageEntry::make('images')
                ->state(function ($record) {
                    return collect($record->images ?? [])
                        ->map(fn ($path) => asset($path))
                        ->toArray();
                })
                ->columnSpanFull()
                ->imageWidth(80)
                ->imageHeight(80)
                ->visible(fn ($record) => !empty($record['images'])),

            Section::make('Основная информация')
                ->schema([
                    IconEntry::make('translation.status')
                        ->label('Has the transfer been made')
                        ->boolean(),

                    TextEntry::make('name')
                        ->size('lg')
                        ->weight('bold')
                        ->belowContent(fn ($record) => $record->translation['name'] ?? null)
                        ->columnSpan(2),

                    TextEntry::make('data.category')
                        ->columnSpan(2),

                    TextEntry::make('price')
                        ->state(fn($record) => "{$record['price']} {$record['currency']['code']}")
                        ->weight('medium')
                        ->color('info'),

                    TextEntry::make('url')
                        ->url(fn ($record) => $record['url'])
                        ->openUrlInNewTab()
                        ->copyable(),

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

            Section::make('Data')
                ->schema([
                    Grid::make(2)
                        ->schema([
                            KeyValueEntry::make('data.attributes'),
                            KeyValueEntry::make('translation_attributes')
                                ->label(function () use ($locale) {
                                    return "Translated attributes from {$locale}";
                                })
                        ])


                ])
                ->columnSpanFull()
                ->collapsible(),

        ]);
    }
}

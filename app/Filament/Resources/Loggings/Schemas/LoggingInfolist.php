<?php

namespace App\Filament\Resources\Loggings\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\Arr;

class LoggingInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Basic information')
                    ->schema([
                        TextEntry::make('id')->label('ID'),
                        TextEntry::make('status')
                            ->label('Статус')
                            ->badge()
                            ->colors([
                                'success' => 'success',
                                'error' => 'danger',
                                'running' => 'warning',
                                'pending' => 'gray',
                            ]),
                        TextEntry::make('type')->label('Тип'),
                        TextEntry::make('parser_class')->label('Класс парсера'),
                        TextEntry::make('message')->label('Сообщение'),
                        TextEntry::make('url')->label('URL')->copyable()->limit(120),
                        TextEntry::make('duration_ms')->label('Длительность (мс)'),
                        TextEntry::make('created_at')->dateTime('Y-m-d H:i:s')->label('Создан'),
                    ])->columns(2),

                KeyValueEntry::make('detail')
                    ->state(function ($record) {
                        $items = Arr::dot($record->context ?? []);

                        foreach ($items as $key => $value) {
                            if (is_array($value) || is_object($value)) {
                                $items[$key] = json_encode($value, JSON_UNESCAPED_UNICODE);
                            } else {
                                $items[$key] = (string) ($value ?? '');
                            }
                        }

                        return $items;
                    }),

            ]);
    }
}

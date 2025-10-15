<?php

namespace App\Filament\Resources\Loggings\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

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

                Section::make('Context')
                    ->collapsible()
                    ->schema([
                        KeyValueEntry::make('context')
                            ->state(function ($record) {
                                $details = [];

                                foreach ($record->context ?? [] as $detailKey => $detailValue) {
                                    if (!is_array($detailValue)) {
                                        $details[$detailKey] = $detailValue;
                                    } else {
                                        foreach ($detailValue as $detailValueKey => $detailValueValue) {
                                            $details["[{$detailKey}] {$detailValueKey}"] = is_array($detailValueValue) ? json_encode($detailValueValue) : $detailValueValue;
                                        }
                                    }
                                }

                                return $details;
                            }),
                    ]),
            ]);
    }
}

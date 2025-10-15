<?php

namespace App\Filament\Resources\Loggings;

use App\Filament\Resources\Loggings\Pages\ListLoggings;
use App\Filament\Resources\Loggings\Pages\ViewLogging;
use App\Filament\Resources\Loggings\Schemas\LoggingInfolist;
use App\Filament\Resources\Loggings\Tables\LoggingsTable;
use App\Models\Logging;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class LoggingResource extends Resource
{
    protected static ?string $model = Logging::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'type';

    public static function infolist(Schema $schema): Schema
    {
        return LoggingInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LoggingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLoggings::route('/'),
            'view' => ViewLogging::route('/{record}'),
        ];
    }
}

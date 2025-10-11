<?php

namespace App\Filament\Resources\Proxies\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ProxyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('host')
                    ->required(),
                TextInput::make('port')
                    ->required(),
                TextInput::make('username'),
                TextInput::make('password')
                    ->password(),
                TextInput::make('scheme')
                    ->required()
                    ->default('https'),
                Toggle::make('is_active')
                    ->required(),
                Textarea::make('note')
                    ->columnSpanFull(),
            ]);
    }
}

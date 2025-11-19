<?php

namespace App\Filament\Resources\Donors\Schemas;

use App\Models\Category;
use App\Services\Parser;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;

class DonorForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('General')
                            ->schema([

                                TextInput::make('name')
                                    ->required()
                                    ->unique(),

                                Select::make('code')
                                    ->options(Parser::availableDonors(true))
                                    ->required(),

                                Repeater::make('pages')
                                    ->hint('We specify links to the catalog or categories (if it is necessary to parse certain ones)')
                                    ->schema([
                                        TextInput::make('path')
                                            ->required(),

                                        Select::make('category_id')
                                            ->label('Object')
                                            ->options(fn() => Category::query()->pluck('name', 'id'))
                                            ->reactive()
                                            ->createOptionForm([
                                                TextInput::make('name') // ⚡ имя колонки в таблице objects
                                                    ->label('Object Name')
                                                    ->required()
                                                    ->unique(), // проверка уникальности по колонке name
                                            ])
                                            ->createOptionUsing(function (array $data): int {
                                                return Category::create([
                                                    'name' => $data['name'],
                                                ])->id;
                                            })
                                            ->required(),

                                    ])
                                    ->required()
                                    ->columns(2),

                                Select::make('setting.language')
                                    ->label('What language is the data in?')
                                    ->hint('Select if the data is not in the default language.')
                                    ->options(config('app.locales', [])),

                                Toggle::make('is_active'),
                            ]),

                            Tab::make('Running')
                                ->schema([

                                    TextInput::make('rate_limit')
                                        ->required()
                                        ->hint('Request limit per minute')
                                        ->numeric()
                                        ->default(30),

                                    TextInput::make('delay_min')
                                        ->required()
                                        ->hint('Minimum delay between requests')
                                        ->numeric()
                                        ->default(1),

                                    TextInput::make('delay_max')
                                        ->required()
                                        ->hint('Maximum delay')
                                        ->numeric()
                                        ->default(5),

                                    TextInput::make('refresh_interval')
                                        ->required()
                                        ->hint('The minimum interval between repeated parsings (in minutes)')
                                        ->numeric()
                                        ->default(3600),

                                    TextInput::make('refresh_interval_sale')
                                        ->required()
                                        ->hint('The minimum interval between complete repeated parsing of prices, balances, etc. (in minutes)')
                                        ->numeric()
                                        ->default(720),

                                ]),


                    ])
                    ->columnSpanFull()

            ]);
    }
}

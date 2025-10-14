<?php

namespace App\Filament\Resources\Donors\Schemas;

use App\Models\Currency;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use PHPUnit\Metadata\Group;

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

                                TextInput::make('code')
                                    ->required(),

                                Select::make('currency_id')
                                    ->label('Currency')
                                    ->required()
                                    ->options(Currency::pluck('code', 'id')),

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

                                Select::make('setting.language')
                                    ->label('What language is the data in?')
                                    ->hint('Select if the data is not in the default language.')
                                    ->options(array_filter(
                                        config('app.locales', []),
                                        fn($name, $code) => $code !== config('app.locale'),
                                        ARRAY_FILTER_USE_BOTH
                                    )),

                                Toggle::make('is_active'),
                            ]),

                        Tab::make('Парсинг каталога')
                            ->schema([

                                TagsInput::make('setting.pages')
                                    ->label('Links to categories')
                                    ->placeholder('New category')
                                    ->hint('We specify links to the catalog or categories (if it is necessary to parse certain ones)')
                                    ->required(),

                                TextInput::make('setting.products.container')
                                    ->label('Product List Container')
                                    ->required()
                                    ->default("//ul[contains(@class,'block-thumbnail-t')]/li[contains(@class,'swiper-slide')]"),

                                Fieldset::make('Container for the url')
                                    ->schema([
                                        TextInput::make('setting.product.container')
                                            ->required()
                                            ->default(".//a"),

                                        TagsInput::make('setting.product.has_url')
                                            ->label('Required content in the product link (or)'),
                                    ])
                                    ->columns(1),

                                Fieldset::make('Container for the price')
                                    ->schema([
                                        TextInput::make('setting.product.price.container')
                                            ->required()
                                            ->default(".//span[contains(@class,'num')]"),

                                        TextInput::make('setting.product.price.regular'),
                                    ])
                                    ->columns(1),

                                Fieldset::make('Container for the pagination')
                                    ->schema([
                                        TextInput::make('setting.pagination.container')
                                            ->required()
                                            ->default("//ul[contains(@class,'pagination')]"),

                                        TagsInput::make('setting.pagination.has_url')
                                            ->label('It should be in the link (or)'),
                                    ])
                                    ->columns(1),

                            ]),

                        Tab::make('Парсинг товара')
                            ->schema([

                                Fieldset::make('Category')
                                    ->schema([
                                        TextInput::make('setting.product_page.category.container')
                                            ->default("//h1[contains(@class,'block-goods-name--text')]"),

                                        TextInput::make('setting.product_page.category.regular'),
                                    ])
                                    ->columns(1),

                                Fieldset::make('Name')
                                    ->schema([
                                        TextInput::make('setting.product_page.name.container')
                                            ->required()
                                            ->default("//h1[contains(@class,'block-goods-name--text')]"),

                                        TextInput::make('setting.product_page.name.regular'),
                                    ])
                                    ->columns(1),

                                Fieldset::make('Images')
                                    ->schema([
                                        TextInput::make('setting.product_page.images.container')
                                            ->required()
                                            ->default("//figure[contains(@class,'block-detail-image-slider--item')]//a[@href]|//figure[contains(@class,'block-detail-image-slider--item')]//img[@data-src]"),

                                        TagsInput::make('setting.product_page.images.has_url')
                                            ->label('It should be in the link (or)'),
                                    ])
                                    ->columns(1),

                                Fieldset::make('Attributes')
                                    ->schema([

                                        TextInput::make('setting.product_page.attributes.container')
                                            ->default("//dl[contains(@class,'goods-spec')]"),

                                        TextInput::make('setting.product_page.attributes.item')
                                            ->default(".//div[contains(@class,'goods-spec-item')]"),

                                        TextInput::make('setting.product_page.attributes.key')
                                            ->default(".//dt"),

                                        TextInput::make('setting.product_page.attributes.value')
                                            ->default(".//dd"),

                                        Fieldset::make('Nested')
                                            ->schema([

                                                TextInput::make('setting.product_page.attributes.nested.container')
                                                    ->default(".//dl[contains(@class,'goods-spec-disc-list')]"),

                                                TextInput::make('setting.product_page.attributes.nested.item')
                                                    ->default(".//div[contains(@class,'goods-spec-disc-list-item')]"),

                                                TextInput::make('setting.product_page.attributes.nested.key')
                                                    ->default(".//dt"),

                                                TextInput::make('setting.product_page.attributes.nested.value')
                                                    ->default(".//dd"),


                                        ])

                                        ->columns(1),

                                    ])
                                    ->columns(1),


                            ]),

                    ])
                    ->columnSpanFull()

            ]);
    }
}

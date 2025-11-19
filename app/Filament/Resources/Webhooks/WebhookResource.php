<?php

namespace App\Filament\Resources\Webhooks;

use App\Filament\Resources\Webhooks\Pages\ManageWebhooks;
use App\Jobs\WebhookCallJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\Webhook;
use App\Services\WebhookService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class WebhookResource extends Resource
{
    protected static ?string $model = Webhook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Tabs')
                    ->tabs([
                        Tab::make('General')
                            ->columns(2)
                            ->schema([

                                TextInput::make('name')
                                    ->required()
                                    ->columnSpanFull(),
                                TextInput::make('url')
                                    ->url()
                                    ->required(),
                                TextInput::make('secret')
                                    ->minValue(15)
                                    ->required(),
                                Select::make('locale')
                                    ->options(config('app.locales'))
                                    ->default(config('app.locale'))
                                    ->required(),
                                Select::make('currency_id')
                                    ->relationship('currency', 'code')
                                    ->searchable()
                                    ->required(),
                                Toggle::make('status')
                                    ->columnSpanFull(),
                            ]),
                        Tab::make('Settings')
                            ->schema([

                                Select::make('setting.category_ids')
                                    ->label('Categories')
                                    ->options(Category::pluck('name', 'id')->toArray())
                                    ->searchable()
                                    ->multiple()
                                    ->hint('Leave it empty to ship products of all categories.'),
                            ]),

                        Tab::make('Price')
                            ->schema([
                                TextInput::make('setting.price_adjustment')
                                    ->label('Default price adjustment')
                                    ->numeric()
                                    ->default(0)
                                    ->suffix('%')
                                    ->columnSpanFull(),

                                Repeater::make('setting.price_adjustment_category')
                                    ->label('Price adjustment by category')
                                    ->schema([
                                        Select::make('category_id')
                                            ->label('Category')
                                            ->options(Category::pluck('name', 'id')->toArray())
                                            ->searchable()
                                            ->required(),

                                        TextInput::make('price')
                                            ->label('Default price adjustment')
                                            ->numeric()
                                            ->default(0)
                                            ->suffix('%')
                                            ->columnSpanFull(),
                                    ])
                                    ->grid(2)

                            ]),

                        Tab::make('Watermarks')
                            ->schema([

                                FileUpload::make('setting.watermark.old')
                                    ->multiple()
                                    ->directory('watermark/old')
                                    ->imageEditor()
                                    ->maxFiles(5)
                                    ->hint('Add watermarks if you want to remove'),

                                FileUpload::make('setting.watermark.new')
                                    ->directory('watermark/new')
                                    ->imageEditor()
                                    ->hint('Add watermarks if you want to remove'),

                                Toggle::make('setting.watermark.is_remove'),

                            ]),

                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('url')
                    ->searchable(),
                TextColumn::make('locale')
                    ->searchable(),
                TextColumn::make('currency.code')
                    ->searchable(),
                ToggleColumn::make('status'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                Action::make('send')
                    ->action(function () {
                        $products = Product::where([
                            ['status', 'active'],
                            ['parsing_status', 'completed'],
                        ])->get();

                        $products->each(function (Product $product) {
                            WebhookCallJob::dispatch($product);
                        });

                        Notification::make()
                            ->title(__('250 items have been sent for processing'))
                            ->success()
                            ->send();
                    }),

                Action::make('ping')
                    ->label(__('Ping'))
                    ->color('warning')
                    ->action(function (Webhook $record, WebhookService $webhookService) {
                        $ping = $webhookService->ping($record);

                        $notification = Notification::make()
                            ->title($ping ? __('Verification completed successfully') : __('Ping failed'));

                        if ($ping) {
                            $notification->success();
                        } else $notification->danger();

                        $notification->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageWebhooks::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Translations;

use App\Filament\Resources\Translations\Pages\ManageTranslations;
use App\Jobs\TranslationJob;
use App\Models\Product;
use App\Models\Translation;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class TranslationResource extends Resource
{
    protected static ?string $model = Translation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'hash';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Textarea::make('source')
                    ->required()
                    ->columnSpanFull()
                    ->disabled(),
                Textarea::make('target')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        set_time_limit(0);

        /*
        Product::all()->each(function (Product $product) {

            if ($product->detail) {
                $details = flattenKeysAndValuesFlexible($product->detail, [ // Исключаем ключи
                    'sku', 'name', 'category', 'description', 'attributes',
                ], [ // Исключаем значения по ключу
                    'sku',
                ]);

                foreach ($details as $detail) {
                    $hash = md5($detail);

                    Translation::firstOrCreate(
                        ['hash' => $hash],
                        [
                            'lang' => $product->donor->setting['language'] ?? config('app.fallback_locale'),
                            'source' => $detail,
                        ]
                    );
                }
            }

        });
*/
        /*
         * Translation::query()
            ->select('id')
            ->chunk(5, function ($chunk) {
                $ids = $chunk->pluck('id')->toArray();
                TranslationJob::dispatch($ids);
            });
         */

        return $table
            ->recordTitleAttribute('source')
            ->columns([
                TextColumn::make('source')
                    ->wrap()
                    ->lineClamp(2)
                    ->searchable(),
                SelectColumn::make('lang')
                    ->options(config('app.locales'))
                    ->afterStateUpdated(function (Translation $record) {
                        $record->update([
                            'target' => null,
                            'target_hash' => null,
                            'target_text' => null,
                        ]);
                    }),
                TextColumn::make('target')
                    ->wrap()
                    ->lineClamp(2)
                    ->searchable(),
                TextColumn::make('canonical.target')
                    ->label('Canonical')
                    ->badge()
                    ->color(function (Translation $record) {
                        return $record->canonical?->target ? 'warning' : 'success';
                    }),
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
                // Показывать только дубликаты
                SelectFilter::make('duplicates')
                    ->label('Дубликаты')
                    ->options([
                        1 => 'Показать только дубликаты',
                    ])
                    ->query(function ($query, $value) {
                        if ($value) {
                            // Дубликаты — это записи, у которых есть canonical_id или повтор target_text
                            $query->whereNotNull('canonical_id')
                                ->orWhereIn('target_text', function ($subQuery) {
                                    $subQuery->select('target_text')
                                        ->from('translations')
                                        ->groupBy('target_text')
                                        ->havingRaw('COUNT(*) > 1');
                                });
                        }
                    }),
            ])
            ->groups([
                // Группировка по target_text, чтобы все дубликаты были рядом
                'target_hash',
            ])
            ->recordActions([

                Action::make('translation')
                    ->label('Translate')
                    ->action(function (Translation $record) {
                        TranslationJob::dispatchSync([$record->id]);
                    }),

                Action::make('setAsCanonical')
                    ->label('The main')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Translation $record) {
                        // Обновляем canonical_id всех дубликатов
                        DB::transaction(function () use ($record) {
                            $duplicates = Translation::where('target_text', $record->target_text)
                                ->where('id', '!=', $record->id)
                                ->get();

                            foreach ($duplicates as $dup) {
                                $dup->update(['canonical_id' => $record->id]);
                            }

                            // Сама запись становится канонической
                            $record->update(['canonical_id' => null]);
                        });
                    }),

                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('translation')
                        ->label('Перевести')
                        ->action(function ($records) {
                            TranslationJob::dispatchSync($records->pluck('id')->toArray());
                        }),

                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTranslations::route('/'),
        ];
    }
}

<?php

namespace App\Filament\Resources\Translations;

use App\Filament\Resources\Translations\Pages\ManageTranslations;
use App\Models\Translation;
use BackedEnum;
use Filament\Actions\Action;
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
                    ->columnSpanFull(),
                Textarea::make('target')
                    ->required()
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('source')
            ->columns([
                TextColumn::make('source')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('target')
                    ->wrap()
                    ->searchable(),
                TextColumn::make('lang')
                    ->state(fn ($record): string => "{$record['from_lang']} -> {$record['to_lang']}"),
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
                            // Дубликаты — это записи, у которых есть canonical_id или повтор normalized_text
                            $query->whereNotNull('canonical_id')
                                ->orWhereIn('normalized_text', function ($subQuery) {
                                    $subQuery->select('normalized_text')
                                        ->from('translations')
                                        ->groupBy('normalized_text', 'to_lang')
                                        ->havingRaw('COUNT(*) > 1');
                                });
                        }
                    }),
            ])
            ->groups([
                // Группировка по normalized_text, чтобы все дубликаты были рядом
                'normalized_text',
            ])
            ->recordActions([

                Action::make('setAsCanonical')
                    ->label('Сделать главным')
                    ->icon('heroicon-o-star')
                    ->color('success')
                    ->requiresConfirmation()
                    ->action(function (Translation $record) {
                        // Обновляем canonical_id всех дубликатов
                        DB::transaction(function () use ($record) {
                            $duplicates = Translation::where('normalized_text', $record->normalized_text)
                                ->where('to_lang', $record->to_lang)
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
            'index' => ManageTranslations::route('/'),
        ];
    }
}

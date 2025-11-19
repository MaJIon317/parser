<?php

namespace App\Livewire;

use App\Models\Product;
use App\Models\ProductLog;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class ProductLogsTable extends Component implements HasActions, HasSchemas, HasTable
{
    use InteractsWithActions, InteractsWithSchemas, InteractsWithTable;

    public ?Product $product = null;

    public function table(Table $table): Table
    {
        return $table
            ->query(
                fn($query) => $this->product->logs()
                    ->orderByDesc('created_at')
            )
            ->columns([
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('type')
                    ->badge()
                    ->sortable(),

                TextColumn::make('code')
                    ->sortable(),

                TextColumn::make('message')
                    ->wrap(),

                TextColumn::make('model')
                    ->label('Related')
                    ->formatStateUsing(function (ProductLog $record) {
                        $model = $record->model;

                        return $model ? implode(': ', [
                            $record->model->getTable(),
                            $model->name
                        ]) : '-';
                    }),


                TextColumn::make('data')
                    ->wrap()
                    ->formatStateUsing(fn ($state) => json_encode($state, JSON_PRETTY_PRINT))
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultGroup('request_id');
    }

    public function render(): View
    {
        return view('livewire.product-logs-table');
    }
}

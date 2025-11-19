<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

class ProductWidget extends StatsOverviewWidget
{
    public function getColumns(): int | array
    {
        return 5;
    }

    protected function getStats(): array
    {
        return [
            StatsOverviewWidget\Stat::make(
                label: 'All Products',
                value: Product::count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'A product with a price greater than zero',
                value: Product::where('price', '>', 0)->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'Active products',
                value: Product::where('status', 'active')->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'Transmitted via the API',
                value: Product::where('status', 'active')
                            ->whereHas('images')
                            ->whereNotNull('price')
                            ->whereNotNull('detail')
                            ->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'Products with details',
                value: Product::whereNotNull('detail')->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'Processed today',
                value: Product::whereDate('last_parsing', '>=', now()->subDay())->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'Products with errors',
                value: Product::whereNotNull('errors')->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'Products without images',
                value: Product::doesntHave('images')->count(),
            ),
        ];
    }
}

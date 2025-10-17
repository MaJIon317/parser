<?php

namespace App\Filament\Widgets;

use App\Models\Product;
use Filament\Widgets\StatsOverviewWidget;
use Illuminate\Support\Facades\DB;

class ProductWidget extends StatsOverviewWidget
{
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
                label: 'Products with details',
                value: Product::whereNotNull('detail')->where('detail', '!=', '[]')->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'All Products',
                value: Product::where('status', 'active')->count(),
            ),
        ];
    }
}

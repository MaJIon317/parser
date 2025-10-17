<?php

namespace App\Filament\Widgets;

use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class QueueWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            StatsOverviewWidget\Stat::make(
                label: 'Number of tasks in the queue',
                value: DB::table('jobs')->count(),
            ),
            StatsOverviewWidget\Stat::make(
                label: 'The number of failed tasks in the queue',
                value: DB::table('failed_jobs')->count(),
            ),
        ];
    }
}

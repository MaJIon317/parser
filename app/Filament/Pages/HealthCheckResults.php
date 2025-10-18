<?php

namespace App\Filament\Pages;

use ShuvroRoy\FilamentSpatieLaravelHealth\Pages\HealthCheckResults as BaseHealthCheckResults;

class HealthCheckResults extends BaseHealthCheckResults
{
    protected static string|null|\BackedEnum $navigationIcon = 'heroicon-o-cpu-chip';

    public function getHeading(): string|\Illuminate\Contracts\Support\Htmlable
    {
        return 'Health Check Results';
    }

    public static function getNavigationGroup(): ?string
    {
        return 'System';
    }
}

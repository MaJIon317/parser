<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class HelpersServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $files = glob(app_path('Helpers') . "/*.php");

        foreach ($files as $key => $file) {
            require_once $file;
        }
    }
}

<?php

namespace App\Providers;

use App\Models\GameMatch;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Route::bind('match', fn (string $value) => GameMatch::query()->findOrFail($value));
    }
}

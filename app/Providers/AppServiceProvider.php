<?php

declare(strict_types=1);

namespace App\Providers;

use App\Models\Debt;
use App\Observers\DebtObserver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Prevent lazy loading in non-production to catch N+1 queries early
        Model::preventLazyLoading(! $this->app->isProduction());

        // Prevent silently discarding attributes that are not fillable
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        // Register model observers
        Debt::observe(DebtObserver::class);
    }
}

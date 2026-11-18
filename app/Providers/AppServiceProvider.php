<?php

namespace App\Providers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\Paginator;
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
        Paginator::useBootstrapFive();

        // Short, stable names in polymorphic columns (activities.subject_type).
        Relation::enforceMorphMap([
            'mill' => \App\Models\Mill::class,
            'buyer' => \App\Models\Buyer::class,
            'sample' => \App\Models\Sample::class,
            'bundle' => \App\Models\SampleBundle::class,
            'dispatch' => \App\Models\SampleDispatch::class,
            'loading' => \App\Models\Loading::class,
        ]);

        // Catch N+1 queries and silently discarded attributes during development.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}

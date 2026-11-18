<?php

namespace App\Providers;

use App\Models\Buyer;
use App\Models\Loading;
use App\Models\Mill;
use App\Models\Sample;
use App\Models\SampleBundle;
use App\Models\SampleDispatch;
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
            'mill' => Mill::class,
            'buyer' => Buyer::class,
            'sample' => Sample::class,
            'bundle' => SampleBundle::class,
            'dispatch' => SampleDispatch::class,
            'loading' => Loading::class,
        ]);

        // Catch N+1 queries and silently discarded attributes during development.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());
    }
}

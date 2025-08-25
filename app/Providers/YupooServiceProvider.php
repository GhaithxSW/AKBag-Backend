<?php

namespace App\Providers;

use App\Services\YupooService;
use Illuminate\Support\ServiceProvider;

class YupooServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        // Merge the Yupoo configuration
        $this->mergeConfigFrom(
            __DIR__.'/../../config/yupoo.php', 'yupoo'
        );

        // Register the YupooService in the service container
        $this->app->singleton(YupooService::class, function ($app) {
            return new YupooService;
        });

        // Register an alias for easier access
        $this->app->alias(YupooService::class, 'yupoo');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        // Publish the configuration file
        $this->publishes([
            __DIR__.'/../../config/yupoo.php' => config_path('yupoo.php'),
        ], 'yupoo-config');
    }
}

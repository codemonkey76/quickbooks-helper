<?php

namespace Codemonkey76\Quickbooks\Providers;

use Codemonkey76\Quickbooks\Http\Middleware\QuickbooksConnected;
use Illuminate\Support\ServiceProvider as LaravelServiceProvider;

/**
 * Class ServiceProvider
 * 
 * @package Codemonkey76\Quickbooks
*/
class ServiceProvider extends LaravelServiceProvider
{
    public function boot()
    {
        $this->registerMiddleware();
        $this->registerPublishes();
        $this->registerRoutes();
        $this->registerViews();
    }

    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/quickbooks.php', 'quickbooks');
    }

    public function registerMiddleware()
    {
        $this->app->router->aliasMiddleware('quickbooks', QuickbooksConnected::class);
    }

    public function registerPublishes()
    {
        if ($this->app->runningInConsole()) {
            $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

            $this->publishes([
                __DIR__ . '/../../config/quickbooks.php' => config_path('quickbooks.php'),
            ], 'quickbooks-config');

            $this->publishes([
                __DIR__ . '/../../database/migrations' => database_path('migrations'),
            ], 'quickbooks-migrations');

            $this->publishes([
                __DIR__ . '/../../resources/views' => base_path('resources/views/vendor/quickbooks'),
            ], 'quickbooks-views');
        }
    }

    public function registerRoutes()
    {

    }

    public function registerViews()
    {

    }

}
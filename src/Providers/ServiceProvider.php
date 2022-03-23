<?php

namespace Codemonkey76\Quickbooks\Providers;

use Codemonkey76\Quickbooks\Http\Middleware\QuickbooksConnected;
use Illuminate\Routing\Router;


/**
 * Class ServiceProvider
 *
 * @package Codemonkey76\Quickbooks
 */
class ServiceProvider extends \Illuminate\Support\ServiceProvider
{
    public function register()
    {
        $this->mergeConfigFrom(__DIR__ . '/../../config/quickbooks.php', 'quickbooks');
    }

    public function boot()
    {
        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations');

        $this
            ->registerMiddleware()
            ->registerPublishes()
            ->registerRoutes()
            ->registerViews();
    }

    public function registerMiddleware(): Self
    {
        $this->app->router->aliasMiddleware('quickbooks', QuickbooksConnected::class);

        return $this;
    }

    public function registerPublishes(): Self
    {
        if ($this->app->runningInConsole())
        {
            $this->loadMigrationsFrom(__DIR__ . '../../database/migrations');

            $this->publishes([__DIR__ . '/../../config/quickbooks.php' => config_path('quickbooks.php')], 'quickbooks-config');
            $this->publishes([__DIR__ . '/../../database/migrations' => database_path('migrations')], 'quickbooks-migrations');
            $this->publishes([__DIR__ . '/../../resources/views' => base_path('resources/views/vendor/quickbooks')],  'quickbooks-views');
        }

        return $this;
    }

    public function registerRoutes(): Self
    {
        $config = $this->app->config->get('quickbooks.route');

        $this->app->router
            ->prefix($config['prefix'])
            ->as('quickbooks.')
            ->middleware($config['middleware']['default'])
            ->namespace('Codemonkey76\Quickbooks\Http\Controllers')
            ->group(function(Router $router) use ($config) {
                $router
                    ->get($config['paths']['connect'], 'Controller@connect')
                    ->middleware($config['middleware']['authenticated'])
                    ->name('connect');

                $router
                    ->delete($config['paths']['disconnect'], 'Controller@disconnect')
                    ->middleware($config['middleware']['authenticated'])
                    ->name('disconnect');

                $router
                    ->get($config['paths']['token'], 'Controller@token')
                    ->name('token');
            });

        return $this;
    }

    public function registerViews(): Self
    {
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'quickbooks');

        return $this;
    }

}
<?php

namespace Codemonkey76\Quickbooks\Providers;

use Illuminate\contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

use Codemonkey76\Quickbooks\QuickbooksClient;

/**
 * Class QuickbooksServiceProvider
 *
 * @package Codemonkey76\Quickbooks
*/
class QuickbooksServiceProvider extends ServiceProvider
{
    protected $defer = true;

    public function provides()
    {
        return [
            QuickbooksClient::class
        ];
    }

    public function register()
    {
        $this->app->bind(QuickbooksClient::class, fn(Application $app) =>
            new QuickbooksClient($app->config->get('quickbooks'), ($app->auth->user()->quickbooksToken) ?: $app->auth->user()->quickbooksToken()->make())
        );

        $this->app->alias(QuickbooksClient::class, 'Quickbooks');
    }


}
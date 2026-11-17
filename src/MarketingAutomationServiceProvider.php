<?php

namespace ItmoSoft\MarketingAutomation;

use Illuminate\Support\ServiceProvider;

class MarketingAutomationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/marketing-automation.php', 'itmosoft-marketing-automation');
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/marketing-automation.php' => config_path('itmosoft-marketing-automation.php'),
            ], 'itmosoft-marketing-automation-config');
        }
    }
}

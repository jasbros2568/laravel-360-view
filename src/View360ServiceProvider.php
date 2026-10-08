<?php

namespace jasbros2568\View360;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use jasbros2568\View360\View\Components\Viewer;

class View360ServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/view360.php', 'view360');

        $this->app->singleton(View360::class);
    }

    public function boot(): void
    {
        $this->loadViewsFrom(__DIR__.'/../resources/views', 'view360');

        Blade::component('view360', Viewer::class);

        if ($this->app->runningInConsole()) {
            $this->publishes([__DIR__.'/../config/view360.php' => config_path('view360.php')], 'view360-config');
            $this->publishes([__DIR__.'/../resources/dist' => public_path('vendor/view360')], ['view360-assets', 'laravel-assets']);
            $this->publishes([__DIR__.'/../resources/views' => resource_path('views/vendor/view360')], 'view360-views');
        }
    }
}

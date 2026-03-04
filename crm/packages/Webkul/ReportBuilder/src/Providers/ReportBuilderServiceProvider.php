<?php

namespace Webkul\ReportBuilder\Providers;

use Illuminate\Support\ServiceProvider;

class ReportBuilderServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../Database/Migrations');
    }

    public function register(): void
    {
        $this->app->singleton(
            \Webkul\ReportBuilder\Services\ReportExecutor::class,
            \Webkul\ReportBuilder\Services\ReportExecutor::class
        );
    }
}

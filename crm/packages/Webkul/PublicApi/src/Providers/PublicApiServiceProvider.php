<?php

namespace Webkul\PublicApi\Providers;

use Illuminate\Support\ServiceProvider;

class PublicApiServiceProvider extends ServiceProvider
{
    public function boot()
    {
        $this->loadRoutesFrom(__DIR__.'/../Http/routes.php');
    }

    public function register() {}
}

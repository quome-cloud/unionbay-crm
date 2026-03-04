<?php

namespace Webkul\ActionStream\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\ActionStream\Models\NextAction::class,
    ];
}

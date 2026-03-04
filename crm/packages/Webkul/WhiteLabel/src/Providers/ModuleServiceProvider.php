<?php

namespace Webkul\WhiteLabel\Providers;

use Webkul\Core\Providers\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\WhiteLabel\Models\WhiteLabelSetting::class,
    ];
}

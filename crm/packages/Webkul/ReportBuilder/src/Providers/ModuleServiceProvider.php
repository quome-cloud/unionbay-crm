<?php

namespace Webkul\ReportBuilder\Providers;

use Konekt\Concord\BaseModuleServiceProvider;

class ModuleServiceProvider extends BaseModuleServiceProvider
{
    protected $models = [
        \Webkul\ReportBuilder\Models\ReportDefinition::class,
    ];
}

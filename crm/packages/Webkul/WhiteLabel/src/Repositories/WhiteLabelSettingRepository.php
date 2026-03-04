<?php

namespace Webkul\WhiteLabel\Repositories;

use Webkul\Core\Eloquent\Repository;

class WhiteLabelSettingRepository extends Repository
{
    protected $fieldSearchable = [
        'app_name',
    ];

    public function model()
    {
        return 'Webkul\WhiteLabel\Contracts\WhiteLabelSetting';
    }

    public function getSettings()
    {
        return $this->first() ?? $this->create([
            'app_name'      => 'CRM',
            'primary_color' => '#1E40AF',
        ]);
    }
}

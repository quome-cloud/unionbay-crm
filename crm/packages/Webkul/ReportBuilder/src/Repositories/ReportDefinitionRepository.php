<?php

namespace Webkul\ReportBuilder\Repositories;

use Prettus\Repository\Eloquent\BaseRepository;
use Webkul\ReportBuilder\Contracts\ReportDefinition;

class ReportDefinitionRepository extends BaseRepository
{
    public function model()
    {
        return ReportDefinition::class;
    }

    public function getForUser(int $userId)
    {
        return $this->scopeQuery(function ($q) use ($userId) {
            return $q->where('user_id', $userId)
                ->orWhere('is_public', true);
        })->orderBy('updated_at', 'desc')->all();
    }
}

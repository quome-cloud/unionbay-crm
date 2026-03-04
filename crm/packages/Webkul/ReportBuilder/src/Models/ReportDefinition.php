<?php

namespace Webkul\ReportBuilder\Models;

use Illuminate\Database\Eloquent\Model;
use Webkul\ReportBuilder\Contracts\ReportDefinition as ReportDefinitionContract;

class ReportDefinition extends Model implements ReportDefinitionContract
{
    protected $table = 'report_definitions';

    protected $fillable = [
        'name',
        'entity_type',
        'columns',
        'filters',
        'group_by',
        'sort_by',
        'sort_order',
        'chart_type',
        'user_id',
        'is_public',
    ];

    protected $casts = [
        'columns'   => 'array',
        'filters'   => 'array',
        'is_public' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(\Webkul\User\Models\User::class);
    }
}

<?php

namespace Webkul\ReportBuilder\Services;

use Illuminate\Support\Facades\DB;

class ReportExecutor
{
    /**
     * Entity type to table/column mapping.
     */
    protected array $entityConfig = [
        'leads' => [
            'table'   => 'leads',
            'columns' => [
                'id'              => ['label' => 'ID', 'type' => 'integer'],
                'title'           => ['label' => 'Title', 'type' => 'string'],
                'lead_value'      => ['label' => 'Value', 'type' => 'decimal'],
                'status'          => ['label' => 'Status', 'type' => 'integer'],
                'pipeline'        => ['label' => 'Pipeline', 'type' => 'string', 'join' => 'lead_pipelines', 'join_col' => 'lead_pipelines.name', 'join_on' => ['leads.lead_pipeline_id', 'lead_pipelines.id']],
                'stage'           => ['label' => 'Stage', 'type' => 'string', 'join' => 'lead_pipeline_stages', 'join_col' => 'lead_pipeline_stages.name', 'join_on' => ['leads.lead_pipeline_stage_id', 'lead_pipeline_stages.id']],
                'person'          => ['label' => 'Contact', 'type' => 'string', 'join' => 'persons', 'join_col' => 'persons.name', 'join_on' => ['leads.person_id', 'persons.id']],
                'created_at'      => ['label' => 'Created', 'type' => 'datetime'],
                'updated_at'      => ['label' => 'Updated', 'type' => 'datetime'],
                'closed_at'       => ['label' => 'Closed', 'type' => 'datetime'],
            ],
        ],
        'contacts' => [
            'table'   => 'persons',
            'columns' => [
                'id'         => ['label' => 'ID', 'type' => 'integer'],
                'name'       => ['label' => 'Name', 'type' => 'string'],
                'emails'     => ['label' => 'Emails', 'type' => 'json'],
                'created_at' => ['label' => 'Created', 'type' => 'datetime'],
                'updated_at' => ['label' => 'Updated', 'type' => 'datetime'],
            ],
        ],
        'activities' => [
            'table'   => 'activities',
            'columns' => [
                'id'           => ['label' => 'ID', 'type' => 'integer'],
                'title'        => ['label' => 'Title', 'type' => 'string'],
                'type'         => ['label' => 'Type', 'type' => 'string'],
                'is_done'      => ['label' => 'Done', 'type' => 'boolean'],
                'schedule_from' => ['label' => 'Scheduled From', 'type' => 'datetime'],
                'schedule_to'  => ['label' => 'Scheduled To', 'type' => 'datetime'],
                'created_at'   => ['label' => 'Created', 'type' => 'datetime'],
            ],
        ],
        'products' => [
            'table'   => 'products',
            'columns' => [
                'id'         => ['label' => 'ID', 'type' => 'integer'],
                'name'       => ['label' => 'Name', 'type' => 'string'],
                'sku'        => ['label' => 'SKU', 'type' => 'string'],
                'created_at' => ['label' => 'Created', 'type' => 'datetime'],
            ],
        ],
    ];

    /**
     * Get available entity types and their columns.
     */
    public function getEntitySchema(): array
    {
        $schema = [];

        foreach ($this->entityConfig as $type => $config) {
            $cols = [];
            foreach ($config['columns'] as $code => $def) {
                $cols[] = [
                    'code'  => $code,
                    'label' => $def['label'],
                    'type'  => $def['type'],
                ];
            }
            $schema[$type] = $cols;
        }

        return $schema;
    }

    /**
     * Execute a report definition and return results.
     */
    public function execute(array $definition, int $limit = 1000): array
    {
        $entityType = $definition['entity_type'];
        $config = $this->entityConfig[$entityType] ?? null;

        if (! $config) {
            return ['rows' => [], 'total' => 0, 'error' => 'Unknown entity type'];
        }

        $table = $config['table'];
        $columns = $definition['columns'] ?? array_keys($config['columns']);
        $filters = $definition['filters'] ?? [];
        $groupBy = $definition['group_by'] ?? null;
        $sortBy = $definition['sort_by'] ?? 'id';
        $sortOrder = $definition['sort_order'] ?? 'desc';

        $query = DB::table($table);

        // Add soft delete filter if applicable
        if (in_array($table, ['leads', 'persons'])) {
            $query->whereNull("{$table}.deleted_at");
        }

        // Build select columns
        $selectCols = [];
        $joins = [];

        foreach ($columns as $col) {
            $colDef = $config['columns'][$col] ?? null;
            if (! $colDef) {
                continue;
            }

            if (isset($colDef['join'])) {
                $joinTable = $colDef['join'];
                if (! in_array($joinTable, $joins)) {
                    $query->leftJoin($joinTable, $colDef['join_on'][0], '=', $colDef['join_on'][1]);
                    $joins[] = $joinTable;
                }
                $selectCols[] = DB::raw("{$colDef['join_col']} as {$col}");
            } else {
                $selectCols[] = "{$table}.{$col}";
            }
        }

        // If grouping, add aggregation
        if ($groupBy && in_array($groupBy, $columns)) {
            $groupCol = $config['columns'][$groupBy] ?? null;
            if ($groupCol) {
                $groupColExpr = isset($groupCol['join']) ? $groupCol['join_col'] : "{$table}.{$groupBy}";
                $selectCols = [
                    DB::raw("{$groupColExpr} as {$groupBy}"),
                    DB::raw('COUNT(*) as count'),
                ];

                // Add sum of value column if available
                if (isset($config['columns']['lead_value'])) {
                    $selectCols[] = DB::raw('SUM(COALESCE(leads.lead_value, 0)) as total_value');
                }

                $query->groupBy(DB::raw($groupColExpr));
            }
        }

        $query->select($selectCols);

        // Apply filters
        foreach ($filters as $filter) {
            $this->applyFilter($query, $table, $config, $filter);
        }

        // Sorting
        $sortColDef = $config['columns'][$sortBy] ?? null;
        if ($sortColDef) {
            $sortExpr = isset($sortColDef['join']) ? $sortColDef['join_col'] : "{$table}.{$sortBy}";
            $query->orderBy(DB::raw($sortExpr), $sortOrder === 'asc' ? 'asc' : 'desc');
        }

        // Get total count (before limit)
        $countQuery = clone $query;
        $total = $groupBy ? $countQuery->get()->count() : $countQuery->count();

        // Apply limit
        $rows = $query->limit($limit)->get()->toArray();

        return [
            'rows'  => $rows,
            'total' => $total,
        ];
    }

    /**
     * Apply a single filter condition to the query.
     */
    protected function applyFilter($query, string $table, array $config, array $filter): void
    {
        $column = $filter['column'] ?? null;
        $operator = $filter['operator'] ?? '=';
        $value = $filter['value'] ?? null;

        if (! $column || $value === null) {
            return;
        }

        $colDef = $config['columns'][$column] ?? null;
        if (! $colDef) {
            return;
        }

        $colExpr = isset($colDef['join']) ? $colDef['join_col'] : "{$table}.{$column}";

        switch ($operator) {
            case '=':
            case '!=':
            case '>':
            case '>=':
            case '<':
            case '<=':
                $query->where(DB::raw($colExpr), $operator, $value);
                break;
            case 'like':
                $query->where(DB::raw($colExpr), 'LIKE', "%{$value}%");
                break;
            case 'in':
                $query->whereIn(DB::raw($colExpr), (array) $value);
                break;
            case 'not_in':
                $query->whereNotIn(DB::raw($colExpr), (array) $value);
                break;
            case 'between':
                if (is_array($value) && count($value) === 2) {
                    $query->whereBetween(DB::raw($colExpr), $value);
                }
                break;
            case 'is_null':
                $query->whereNull(DB::raw($colExpr));
                break;
            case 'is_not_null':
                $query->whereNotNull(DB::raw($colExpr));
                break;
        }
    }
}

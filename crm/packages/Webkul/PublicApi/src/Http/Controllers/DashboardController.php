<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $userId = $request->get('user_id');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        // Parse dates
        $from = $dateFrom ? Carbon::parse($dateFrom)->startOfDay() : Carbon::now()->startOfMonth();
        $to = $dateTo ? Carbon::parse($dateTo)->endOfDay() : Carbon::now()->endOfDay();

        return response()->json([
            'data' => [
                'leads'      => $this->getLeadStats($userId, $from, $to),
                'activities'  => $this->getActivityStats($userId, $from, $to),
                'revenue'     => $this->getRevenueStats($userId, $from, $to),
                'pipeline'    => $this->getPipelineStats($userId),
                'date_range'  => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
            ],
        ]);
    }

    protected function getLeadStats(?string $userId, Carbon $from, Carbon $to): array
    {
        $query = DB::table('leads')->whereNull('deleted_at');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = (clone $query)->count();
        $created = (clone $query)->whereBetween('created_at', [$from, $to])->count();
        $active = (clone $query)->where('status', 1)->count();

        $won = (clone $query)
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('lead_pipeline_stages.code', 'won')
            ->whereBetween('leads.closed_at', [$from, $to])
            ->count();

        $lost = (clone $query)
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('lead_pipeline_stages.code', 'lost')
            ->whereBetween('leads.closed_at', [$from, $to])
            ->count();

        return [
            'total'   => $total,
            'created' => $created,
            'active'  => $active,
            'won'     => $won,
            'lost'    => $lost,
        ];
    }

    protected function getActivityStats(?string $userId, Carbon $from, Carbon $to): array
    {
        $query = DB::table('activities');

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $total = (clone $query)->whereBetween('created_at', [$from, $to])->count();
        $completed = (clone $query)->whereBetween('created_at', [$from, $to])->where('is_done', 1)->count();
        $overdue = (clone $query)->where('is_done', 0)
            ->where('schedule_to', '<', Carbon::now())
            ->count();

        $byType = (clone $query)->whereBetween('created_at', [$from, $to])
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->get()
            ->pluck('count', 'type')
            ->toArray();

        return [
            'total'     => $total,
            'completed' => $completed,
            'overdue'   => $overdue,
            'by_type'   => $byType,
        ];
    }

    protected function getRevenueStats(?string $userId, Carbon $from, Carbon $to): array
    {
        $query = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->where('lead_pipeline_stages.code', 'won');

        if ($userId) {
            $query->where('leads.user_id', $userId);
        }

        $wonRevenue = (clone $query)
            ->whereBetween('leads.closed_at', [$from, $to])
            ->sum('leads.lead_value');

        $totalWonRevenue = (clone $query)->sum('leads.lead_value');

        // Pipeline value (active deals)
        $pipelineQuery = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.status', 1)
            ->whereNotIn('lead_pipeline_stages.code', ['won', 'lost']);

        if ($userId) {
            $pipelineQuery->where('leads.user_id', $userId);
        }

        $pipelineValue = $pipelineQuery->sum('leads.lead_value');

        return [
            'won_this_period' => round($wonRevenue, 2),
            'won_all_time'    => round($totalWonRevenue, 2),
            'pipeline_value'  => round($pipelineValue, 2),
        ];
    }

    protected function getPipelineStats(?string $userId): array
    {
        $query = DB::table('leads')
            ->join('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNull('leads.deleted_at')
            ->where('leads.status', 1)
            ->whereNotIn('lead_pipeline_stages.code', ['won', 'lost']);

        if ($userId) {
            $query->where('leads.user_id', $userId);
        }

        $stages = $query->select(
            'lead_pipeline_stages.name as stage_name',
            DB::raw('COUNT(leads.id) as deal_count'),
            DB::raw('SUM(COALESCE(leads.lead_value, 0)) as total_value')
        )
        ->groupBy('lead_pipeline_stages.name', 'lead_pipeline_stages.sort_order')
        ->orderBy('lead_pipeline_stages.sort_order')
        ->get();

        return $stages->toArray();
    }
}

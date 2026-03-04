<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ActivityReportController extends Controller
{
    public function summary(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'user_id'   => 'sometimes|integer|exists:users,id',
        ]);

        $from = $request->get('date_from')
            ? Carbon::parse($request->get('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->get('date_to')
            ? Carbon::parse($request->get('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();
        $userId = $request->get('user_id');

        $query = DB::table('activities')
            ->whereBetween('created_at', [$from, $to]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $byType = (clone $query)
            ->select('type', DB::raw('COUNT(*) as count'))
            ->groupBy('type')
            ->pluck('count', 'type')
            ->toArray();

        $total = (clone $query)->count();
        $completed = (clone $query)->where('is_done', 1)->count();

        return response()->json([
            'data' => [
                'total'          => $total,
                'completed'      => $completed,
                'completion_rate' => $total > 0 ? round(($completed / $total) * 100, 1) : 0,
                'by_type'        => $byType,
                'date_range'     => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
            ],
        ]);
    }

    public function byUser(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
        ]);

        $from = $request->get('date_from')
            ? Carbon::parse($request->get('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->get('date_to')
            ? Carbon::parse($request->get('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();

        $users = DB::table('activities')
            ->join('users', 'activities.user_id', '=', 'users.id')
            ->whereBetween('activities.created_at', [$from, $to])
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN activities.is_done = 1 THEN 1 ELSE 0 END) as completed'),
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();

        $result = $users->map(function ($user) {
            return [
                'user_id'         => $user->user_id,
                'user_name'       => $user->user_name,
                'total'           => (int) $user->total,
                'completed'       => (int) $user->completed,
                'completion_rate' => $user->total > 0 ? round(($user->completed / $user->total) * 100, 1) : 0,
            ];
        });

        return response()->json([
            'data' => [
                'users'      => $result,
                'date_range' => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
            ],
        ]);
    }

    public function leaderboard(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'metric'    => 'sometimes|in:total,completed,completion_rate',
        ]);

        $from = $request->get('date_from')
            ? Carbon::parse($request->get('date_from'))->startOfDay()
            : Carbon::now()->startOfMonth();
        $to = $request->get('date_to')
            ? Carbon::parse($request->get('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();
        $metric = $request->get('metric', 'completed');

        $users = DB::table('activities')
            ->join('users', 'activities.user_id', '=', 'users.id')
            ->whereBetween('activities.created_at', [$from, $to])
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN activities.is_done = 1 THEN 1 ELSE 0 END) as completed'),
            )
            ->groupBy('users.id', 'users.name')
            ->get();

        $ranked = $users->map(function ($user) {
            return [
                'user_id'         => $user->user_id,
                'user_name'       => $user->user_name,
                'total'           => (int) $user->total,
                'completed'       => (int) $user->completed,
                'completion_rate' => $user->total > 0 ? round(($user->completed / $user->total) * 100, 1) : 0,
            ];
        });

        // Sort by metric
        $sorted = $ranked->sortByDesc($metric)->values();

        // Add rank
        $leaderboard = $sorted->map(function ($item, $index) {
            return array_merge($item, ['rank' => $index + 1]);
        });

        return response()->json([
            'data' => [
                'leaderboard' => $leaderboard,
                'metric'      => $metric,
                'date_range'  => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
            ],
        ]);
    }

    public function trends(Request $request): JsonResponse
    {
        $request->validate([
            'date_from' => 'sometimes|date',
            'date_to'   => 'sometimes|date',
            'user_id'   => 'sometimes|integer|exists:users,id',
            'interval'  => 'sometimes|in:day,week,month',
        ]);

        $from = $request->get('date_from')
            ? Carbon::parse($request->get('date_from'))->startOfDay()
            : Carbon::now()->subDays(30)->startOfDay();
        $to = $request->get('date_to')
            ? Carbon::parse($request->get('date_to'))->endOfDay()
            : Carbon::now()->endOfDay();
        $userId = $request->get('user_id');
        $interval = $request->get('interval', 'day');

        $dateFormat = match ($interval) {
            'day'   => '%Y-%m-%d',
            'week'  => '%x-W%v',
            'month' => '%Y-%m',
        };

        $query = DB::table('activities')
            ->whereBetween('created_at', [$from, $to]);

        if ($userId) {
            $query->where('user_id', $userId);
        }

        $trends = $query
            ->select(
                DB::raw("DATE_FORMAT(created_at, '{$dateFormat}') as period"),
                DB::raw('COUNT(*) as total'),
                DB::raw('SUM(CASE WHEN is_done = 1 THEN 1 ELSE 0 END) as completed'),
            )
            ->groupBy('period')
            ->orderBy('period')
            ->get();

        return response()->json([
            'data' => [
                'trends'     => $trends,
                'interval'   => $interval,
                'date_range' => [
                    'from' => $from->toDateString(),
                    'to'   => $to->toDateString(),
                ],
            ],
        ]);
    }
}

<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Activity\Repositories\ActivityRepository;

class ActivityController extends Controller
{
    public function __construct(
        protected ActivityRepository $activityRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->activityRepository->scopeQuery(function ($q) use ($request) {
            if ($type = $request->get('type')) {
                $q = $q->where('type', $type);
            }
            if ($userId = $request->get('user_id')) {
                $q = $q->where('user_id', $userId);
            }
            return $q->orderBy($request->get('sort', 'id'), $request->get('order', 'desc'));
        });

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $activity = $this->activityRepository->findOrFail($id);

        return response()->json(['data' => $activity]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'type'       => 'required|string|in:call,meeting,lunch,note',
            'schedule_from' => 'sometimes|date',
            'schedule_to'   => 'sometimes|date',
        ]);

        $data = $request->all();
        $data['user_id'] = $request->user()->id;
        $data['is_done'] = 0;

        $activity = $this->activityRepository->create($data);

        return response()->json(['data' => $activity, 'message' => 'Activity created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $activity = $this->activityRepository->update($request->all(), $id);

        return response()->json(['data' => $activity, 'message' => 'Activity updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->activityRepository->findOrFail($id);
        $this->activityRepository->delete($id);

        return response()->json(['message' => 'Activity deleted.']);
    }
}

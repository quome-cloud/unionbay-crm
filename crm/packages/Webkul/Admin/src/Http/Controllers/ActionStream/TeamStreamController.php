<?php

namespace Webkul\Admin\Http\Controllers\ActionStream;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Webkul\Admin\Http\Controllers\Controller;
use Webkul\ActionStream\Repositories\NextActionRepository;
use Webkul\User\Repositories\UserRepository;

class TeamStreamController extends Controller
{
    public function __construct(
        protected NextActionRepository $nextActionRepository,
        protected UserRepository $userRepository
    ) {}

    public function index(): View
    {
        return view('admin::action-stream.team');
    }

    public function members(): JsonResponse
    {
        $users = $this->userRepository->findWhere([['status', '=', 1]], ['id', 'name', 'email']);

        return response()->json(['data' => $users]);
    }

    public function stream(Request $request): JsonResponse
    {
        $filters = $request->only(['action_type', 'priority', 'due_from', 'due_to', 'user_id', 'status']);

        $userIds = $this->userRepository->findWhere([['status', '=', 1]])->pluck('id')->toArray();

        if (empty($userIds)) {
            $userIds = [auth()->guard('user')->id()];
        }

        $query = $this->nextActionRepository->getTeamActions($userIds, $filters);
        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }
}

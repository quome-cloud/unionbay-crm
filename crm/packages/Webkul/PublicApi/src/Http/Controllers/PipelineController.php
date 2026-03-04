<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\Lead\Repositories\StageRepository;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineRepository $pipelineRepository,
        protected StageRepository $stageRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->pipelineRepository->scopeQuery(function ($q) use ($request) {
            if ($type = $request->get('type')) {
                $q = $q->where('pipeline_type', $type);
            }

            return $q->orderBy('id');
        });

        $pipelines = $query->all();

        return response()->json(['data' => $pipelines]);
    }

    public function show(int $id): JsonResponse
    {
        $pipeline = $this->pipelineRepository->findOrFail($id);
        $pipeline->load('stages');

        return response()->json(['data' => $pipeline]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'          => 'required|string|max:255|unique:lead_pipelines,name',
            'pipeline_type' => 'sometimes|in:sales,delivery',
            'rotten_days'   => 'sometimes|integer|min:1',
            'stages'        => 'required|array|min:1',
            'stages.*.name' => 'required|string|max:255',
            'stages.*.code' => 'required|string|max:255',
            'stages.*.probability' => 'sometimes|integer|min:0|max:100',
        ]);

        $data = $request->all();
        $data['pipeline_type'] = $data['pipeline_type'] ?? 'sales';
        $data['rotten_days'] = $data['rotten_days'] ?? 30;
        $data['is_default'] = $data['is_default'] ?? 0;

        $pipeline = $this->pipelineRepository->create($data);
        $pipeline->load('stages');

        return response()->json(['data' => $pipeline, 'message' => 'Pipeline created.'], 201);
    }
}

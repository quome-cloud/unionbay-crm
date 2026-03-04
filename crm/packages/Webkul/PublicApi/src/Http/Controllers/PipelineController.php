<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller;
use Webkul\Lead\Repositories\PipelineRepository;

class PipelineController extends Controller
{
    public function __construct(
        protected PipelineRepository $pipelineRepository
    ) {}

    public function index(): JsonResponse
    {
        $pipelines = $this->pipelineRepository->all();

        return response()->json(['data' => $pipelines]);
    }

    public function show(int $id): JsonResponse
    {
        $pipeline = $this->pipelineRepository->findOrFail($id);
        $pipeline->load('stages');

        return response()->json(['data' => $pipeline]);
    }
}

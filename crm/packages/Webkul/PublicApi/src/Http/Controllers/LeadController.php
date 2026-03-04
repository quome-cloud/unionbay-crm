<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Lead\Repositories\LeadRepository;

class LeadController extends Controller
{
    public function __construct(
        protected LeadRepository $leadRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->leadRepository->scopeQuery(function ($q) use ($request) {
            if ($search = $request->get('search')) {
                $q = $q->where('title', 'like', "%{$search}%");
            }
            if ($pipeline = $request->get('pipeline_id')) {
                $q = $q->where('lead_pipeline_id', $pipeline);
            }
            if ($stage = $request->get('stage_id')) {
                $q = $q->where('lead_pipeline_stage_id', $stage);
            }
            return $q->orderBy($request->get('sort', 'id'), $request->get('order', 'desc'));
        });

        $perPage = min((int) $request->get('per_page', 15), 100);

        return response()->json($query->paginate($perPage));
    }

    public function show(int $id): JsonResponse
    {
        $lead = $this->leadRepository->findOrFail($id);

        return response()->json(['data' => $lead]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'title'                  => 'required|string|max:255',
            'lead_value'             => 'sometimes|numeric',
            'lead_pipeline_id'       => 'required|exists:lead_pipelines,id',
            'lead_pipeline_stage_id' => 'required|exists:lead_pipeline_stages,id',
            'person_id'              => 'sometimes|exists:persons,id',
        ]);

        $data = $request->all();
        $data['entity_type'] = 'leads';
        $data['user_id'] = $request->user()->id;
        $data['status'] = $data['status'] ?? 1;

        $lead = $this->leadRepository->create($data);

        return response()->json(['data' => $lead, 'message' => 'Lead created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'title'      => 'sometimes|string|max:255',
            'lead_value' => 'sometimes|numeric',
        ]);

        $data = $request->all();
        $data['entity_type'] = 'leads';

        $lead = $this->leadRepository->update($data, $id);

        return response()->json(['data' => $lead, 'message' => 'Lead updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->leadRepository->findOrFail($id);
        $this->leadRepository->delete($id);

        return response()->json(['message' => 'Lead deleted.']);
    }
}

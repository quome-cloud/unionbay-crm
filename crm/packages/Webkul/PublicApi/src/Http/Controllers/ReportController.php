<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\ReportBuilder\Repositories\ReportDefinitionRepository;
use Webkul\ReportBuilder\Services\ReportExecutor;

class ReportController extends Controller
{
    public function __construct(
        protected ReportDefinitionRepository $reportRepo,
        protected ReportExecutor $executor
    ) {}

    /**
     * List available entity types and their columns.
     */
    public function schema(): JsonResponse
    {
        return response()->json(['data' => $this->executor->getEntitySchema()]);
    }

    /**
     * List saved report definitions for the current user.
     */
    public function index(Request $request): JsonResponse
    {
        $reports = $this->reportRepo->getForUser($request->user()->id);

        return response()->json(['data' => $reports]);
    }

    /**
     * Get a single report definition.
     */
    public function show(int $id, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        // Check access
        if ($report->user_id !== $request->user()->id && ! $report->is_public) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        return response()->json(['data' => $report]);
    }

    /**
     * Save a new report definition.
     */
    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'entity_type' => 'required|in:leads,contacts,activities,products',
            'columns'     => 'required|array|min:1',
            'filters'     => 'sometimes|array',
            'group_by'    => 'sometimes|nullable|string',
            'sort_by'     => 'sometimes|nullable|string',
            'sort_order'  => 'sometimes|in:asc,desc',
            'chart_type'  => 'sometimes|nullable|in:bar,line,pie,table',
            'is_public'   => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'name', 'entity_type', 'columns', 'filters',
            'group_by', 'sort_by', 'sort_order', 'chart_type', 'is_public',
        ]);
        $data['user_id'] = $request->user()->id;

        $report = $this->reportRepo->create($data);

        return response()->json(['data' => $report, 'message' => 'Report saved.'], 201);
    }

    /**
     * Update an existing report definition.
     */
    public function update(Request $request, int $id): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $request->validate([
            'name'        => 'sometimes|string|max:255',
            'columns'     => 'sometimes|array|min:1',
            'filters'     => 'sometimes|array',
            'group_by'    => 'sometimes|nullable|string',
            'sort_by'     => 'sometimes|nullable|string',
            'sort_order'  => 'sometimes|in:asc,desc',
            'chart_type'  => 'sometimes|nullable|in:bar,line,pie,table',
            'is_public'   => 'sometimes|boolean',
        ]);

        $data = $request->only([
            'name', 'columns', 'filters',
            'group_by', 'sort_by', 'sort_order', 'chart_type', 'is_public',
        ]);

        $this->reportRepo->update($data, $id);
        $report = $this->reportRepo->find($id);

        return response()->json(['data' => $report, 'message' => 'Report updated.']);
    }

    /**
     * Delete a report definition.
     */
    public function destroy(int $id, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        if ($report->user_id !== $request->user()->id) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $this->reportRepo->delete($id);

        return response()->json(['message' => 'Report deleted.']);
    }

    /**
     * Execute a report (either saved or ad-hoc).
     */
    public function execute(Request $request): JsonResponse
    {
        $request->validate([
            'entity_type' => 'required|in:leads,contacts,activities,products',
            'columns'     => 'required|array|min:1',
            'filters'     => 'sometimes|array',
            'group_by'    => 'sometimes|nullable|string',
            'sort_by'     => 'sometimes|nullable|string',
            'sort_order'  => 'sometimes|in:asc,desc',
            'limit'       => 'sometimes|integer|min:1|max:10000',
        ]);

        $definition = $request->only([
            'entity_type', 'columns', 'filters',
            'group_by', 'sort_by', 'sort_order',
        ]);

        $limit = (int) $request->get('limit', 1000);
        $result = $this->executor->execute($definition, $limit);

        return response()->json(['data' => $result]);
    }

    /**
     * Execute a saved report by ID.
     */
    public function executeSaved(int $id, Request $request): JsonResponse
    {
        $report = $this->reportRepo->findOrFail($id);

        if ($report->user_id !== $request->user()->id && ! $report->is_public) {
            return response()->json(['message' => 'Forbidden'], 403);
        }

        $limit = (int) $request->get('limit', 1000);
        $result = $this->executor->execute($report->toArray(), $limit);

        return response()->json(['data' => $result]);
    }
}

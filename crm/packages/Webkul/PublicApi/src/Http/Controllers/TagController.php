<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Tag\Repositories\TagRepository;

class TagController extends Controller
{
    public function __construct(
        protected TagRepository $tagRepository
    ) {}

    public function index(): JsonResponse
    {
        $tags = $this->tagRepository->all();

        return response()->json(['data' => $tags]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'  => 'required|string|max:255',
            'color' => 'sometimes|string|max:7',
        ]);

        $data = $request->all();
        $data['user_id'] = $request->user()->id;

        $tag = $this->tagRepository->create($data);

        return response()->json(['data' => $tag, 'message' => 'Tag created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $tag = $this->tagRepository->update($request->all(), $id);

        return response()->json(['data' => $tag, 'message' => 'Tag updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->tagRepository->findOrFail($id);
        $this->tagRepository->delete($id);

        return response()->json(['message' => 'Tag deleted.']);
    }
}

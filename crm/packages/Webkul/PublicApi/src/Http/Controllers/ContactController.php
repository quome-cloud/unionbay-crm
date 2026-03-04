<?php

namespace Webkul\PublicApi\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Webkul\Contact\Repositories\PersonRepository;

class ContactController extends Controller
{
    public function __construct(
        protected PersonRepository $personRepository
    ) {}

    public function index(Request $request): JsonResponse
    {
        $query = $this->personRepository->scopeQuery(function ($q) use ($request) {
            if ($search = $request->get('search')) {
                $q = $q->where('name', 'like', "%{$search}%")
                    ->orWhere('emails', 'like', "%{$search}%");
            }
            return $q->orderBy($request->get('sort', 'id'), $request->get('order', 'desc'));
        });

        $perPage = min((int) $request->get('per_page', 15), 100);
        $persons = $query->paginate($perPage);

        return response()->json($persons);
    }

    public function show(int $id): JsonResponse
    {
        $person = $this->personRepository->findOrFail($id);

        return response()->json(['data' => $person]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'name'   => 'required|string|max:255',
            'emails' => 'sometimes|array',
            'contact_numbers' => 'sometimes|array',
        ]);

        $data = $request->all();
        $data['entity_type'] = 'persons';
        $data['user_id'] = $request->user()->id;

        // Ensure emails have proper structure
        if (empty($data['emails'])) {
            $data['emails'] = [['value' => '', 'label' => 'work']];
        }

        $person = $this->personRepository->create($data);

        return response()->json(['data' => $person, 'message' => 'Contact created.'], 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'name' => 'sometimes|string|max:255',
        ]);

        $existing = $this->personRepository->findOrFail($id);

        $data = $request->all();
        $data['entity_type'] = 'persons';
        $data['user_id'] = $data['user_id'] ?? $existing->user_id ?? $request->user()->id;

        // PersonRepository requires emails for unique_id generation
        if (empty($data['emails'])) {
            $data['emails'] = $existing->emails ?? [['value' => '', 'label' => 'work']];
        }

        // Only pass contact_numbers if explicitly provided in the request
        if (! $request->has('contact_numbers')) {
            unset($data['contact_numbers']);
        }

        $this->personRepository->update($data, $id);

        $person = $this->personRepository->find($id);

        return response()->json(['data' => $person, 'message' => 'Contact updated.']);
    }

    public function destroy(int $id): JsonResponse
    {
        $this->personRepository->findOrFail($id);
        $this->personRepository->delete($id);

        return response()->json(['message' => 'Contact deleted.']);
    }
}

<?php

namespace App\Http\Controllers;

use App\Enums\Gender;
use App\Models\Entity;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SearchSuggestionController extends Controller
{
    /**
     * Entities matching the query by name, alias, element, region or origin.
     */
    public function __invoke(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['required', 'string', 'max:100'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'element' => ['nullable', 'string', 'max:255'],
        ]);

        $entities = Entity::query()
            ->search($validated['q'])
            ->when($validated['gender'] ?? null, fn ($query, string $gender) => $query->where('gender', $gender))
            ->when($validated['element'] ?? null, fn ($query, string $element) => $query->where('element', $element))
            ->with('religion')
            ->orderBy('name')
            ->limit(12)
            ->get();

        return response()->json([
            'data' => $entities->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'religion' => $entity->religion->name,
                'gender' => $entity->gender?->label(),
                'element' => $entity->element,
                'region' => $entity->region,
                'aliases' => $entity->aliases ?? [],
                'url' => route('entities.show', $entity),
                'graphUrl' => route('graph.entities', $entity),
            ]),
        ]);
    }
}

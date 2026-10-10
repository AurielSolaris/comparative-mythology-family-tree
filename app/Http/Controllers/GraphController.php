<?php

namespace App\Http\Controllers;

use App\Http\Requests\CompareReligionsRequest;
use App\Models\Entity;
use App\Models\Religion;
use App\Support\EntityGraph;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class GraphController extends Controller
{
    public function entity(Request $request, Entity $entity, EntityGraph $graph): JsonResponse
    {
        $validated = $request->validate([
            'depth' => ['sometimes', 'integer', 'between:1,4'],
        ]);

        return response()->json($graph->around($entity, (int) ($validated['depth'] ?? 2)));
    }

    public function religion(Religion $religion, EntityGraph $graph): JsonResponse
    {
        return response()->json($graph->forReligion($religion));
    }

    public function compare(CompareReligionsRequest $request, EntityGraph $graph): JsonResponse
    {
        return response()->json($graph->compare($request->religions()));
    }
}

<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreEntityRelationRequest;
use App\Models\Entity;
use App\Models\EntityRelation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

class EntityRelationController extends Controller
{
    public function store(StoreEntityRelationRequest $request, Entity $entity): RedirectResponse
    {
        $related = $request->relatedEntity();

        EntityRelation::create([
            ...EntityRelation::attributesFor($entity, $request->role(), $related),
            'religion_id' => $request->validated('religion_id'),
            'note' => $request->validated('note'),
        ]);

        return redirect()->to(route('admin.entities.edit', $entity).'#relations')
            ->with('status', "{$entity->name}: {$request->role()->label($entity->gender)} {$related->name}.");
    }

    /**
     * Flips which partner or sibling the tree draws on the left.
     */
    public function swapSides(EntityRelation $relation): JsonResponse
    {
        abort_unless($relation->hasSwappableSides(), 422, 'Only consort and sibling relations have sides.');

        $relation->swapSides();
        [$from, $to] = $relation->displayOrder();

        return response()->json(['id' => $relation->id, 'from' => $from, 'to' => $to]);
    }

    public function destroy(EntityRelation $relation): RedirectResponse
    {
        $relation->delete();

        return redirect()->to(url()->previous().'#relations')->with('status', 'Relation removed.');
    }
}

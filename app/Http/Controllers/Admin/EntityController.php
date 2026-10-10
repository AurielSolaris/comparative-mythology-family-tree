<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Gender;
use App\Enums\RelationRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\EntityRequest;
use App\Http\Requests\Admin\ReorderEntitiesRequest;
use App\Models\Entity;
use App\Models\Religion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EntityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'religion' => ['nullable', 'integer'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
        ]);

        $entities = Entity::query()
            ->with(['religion', 'additionalReligions'])
            ->withCount(['outgoingRelations', 'incomingRelations'])
            ->when($filters['q'] ?? null, fn ($query, string $term) => $query->search($term))
            ->when($filters['religion'] ?? null, fn ($query, int $religionId) => $query->inReligion($religionId))
            ->when($filters['gender'] ?? null, fn ($query, string $gender) => $query->where('gender', $gender))
            ->ordered()
            ->paginate(25)
            ->withQueryString();

        return view('admin.entities.index', [
            'entities' => $entities,
            'filters' => $filters,
            'religions' => Religion::query()->orderBy('name')->get(),
            'genders' => Gender::cases(),
        ]);
    }

    public function create(Request $request): View
    {
        return view('admin.entities.create', [
            'entity' => new Entity(['religion_id' => $request->integer('religion') ?: null]),
            ...$this->formOptions(),
        ]);
    }

    public function store(EntityRequest $request): RedirectResponse
    {
        $entity = DB::transaction(function () use ($request) {
            $entity = Entity::create($request->safe()->except('additional_religion_ids'));
            $entity->additionalReligions()->sync($request->validated('additional_religion_ids'));

            return $entity;
        });

        return redirect()->route('admin.entities.edit', $entity)
            ->with('status', "Added {$entity->name}. You can now add its relations.");
    }

    public function edit(Entity $entity): View
    {
        return view('admin.entities.edit', [
            'entity' => $entity->loadRelatives()->load('additionalReligions'),
            'roles' => RelationRole::cases(),
            ...$this->formOptions(),
        ]);
    }

    public function update(EntityRequest $request, Entity $entity): RedirectResponse
    {
        DB::transaction(function () use ($request, $entity) {
            $entity->update($request->safe()->except('additional_religion_ids'));
            $entity->additionalReligions()->sync($request->validated('additional_religion_ids'));
        });

        return redirect()->route('admin.entities.edit', $entity)->with('status', "Saved {$entity->name}.");
    }

    /**
     * Saves the order admins dragged the rows into.
     */
    public function reorder(ReorderEntitiesRequest $request): JsonResponse
    {
        Entity::reorder($request->validated('ids'));

        return response()->json(['ok' => true]);
    }

    public function destroy(Entity $entity): RedirectResponse
    {
        $entity->delete();

        return redirect()->route('admin.entities.index')->with('status', "Deleted {$entity->name} and its relations.");
    }

    /**
     * The religion choices, plus regions already in use as suggestions.
     *
     * @return array{religions: \Illuminate\Database\Eloquent\Collection<int, Religion>, genders: list<Gender>, regions: Collection<int, string>}
     */
    protected function formOptions(): array
    {
        return [
            'religions' => Religion::query()->orderBy('name')->get(),
            'genders' => Gender::cases(),
            'regions' => Entity::query()->whereNotNull('region')->distinct()->orderBy('region')->pluck('region'),
        ];
    }
}

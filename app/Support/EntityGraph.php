<?php

namespace App\Support;

use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use Illuminate\Support\Collection;

/**
 * Builds the node/edge graphs the public canvas explorer draws.
 *
 * Parent edges point from parent to child and council edges from the head
 * of the council or court to the member. Consort, sibling and equivalent
 * edges are undirected; for them "from" is the entity drawn on the left.
 */
class EntityGraph
{
    /**
     * Upper bound on nodes in one response, so a huge tree stays drawable.
     */
    public const MAX_NODES = 250;

    /**
     * The entity and everything within $depth relation hops of it.
     *
     * @return array<string, mixed>
     */
    public function around(Entity $focus, int $depth): array
    {
        $ids = [$focus->id => true];
        $frontier = [$focus->id];
        $truncated = false;

        for ($hop = 0; $hop < $depth && $frontier !== []; $hop++) {
            $relations = EntityRelation::query()
                ->whereIn('entity_id', $frontier)
                ->orWhereIn('related_entity_id', $frontier)
                ->get(['entity_id', 'related_entity_id']);

            $frontier = [];

            foreach ($relations as $relation) {
                foreach ([$relation->entity_id, $relation->related_entity_id] as $id) {
                    if (isset($ids[$id])) {
                        continue;
                    }

                    if (count($ids) >= self::MAX_NODES) {
                        $truncated = true;

                        continue;
                    }

                    $ids[$id] = true;
                    $frontier[] = $id;
                }
            }
        }

        return $this->build(array_keys($ids), $truncated) + ['focus' => $focus->id, 'lanes' => null];
    }

    /**
     * Every member of the religion (primary or additional), plus their
     * equivalents in other religions.
     *
     * @return array<string, mixed>
     */
    public function forReligion(Religion $religion): array
    {
        [$ids, $truncated] = $this->membersOf([$religion->id]);

        $equivalents = EntityRelation::query()
            ->where('kind', RelationKind::Equivalent)
            ->where(fn ($query) => $query->whereIn('entity_id', $ids)->orWhereIn('related_entity_id', $ids))
            ->get(['entity_id', 'related_entity_id']);

        foreach ($equivalents as $relation) {
            foreach ([$relation->entity_id, $relation->related_entity_id] as $id) {
                if (count($ids) >= self::MAX_NODES) {
                    $truncated = true;
                    break 2;
                }

                $ids[] = $id;
            }
        }

        return $this->build(array_values(array_unique($ids)), $truncated) + ['focus' => null, 'lanes' => null];
    }

    /**
     * Several religions side by side: their members, each placed in one
     * lane (its primary religion if selected, else the first selected one it
     * also belongs to), and every relation between them.
     *
     * @param  Collection<int, Religion>  $religions  in display order, left to right
     * @return array<string, mixed>
     */
    public function compare(Collection $religions): array
    {
        [$ids, $truncated] = $this->membersOf($religions->modelKeys());
        $graph = $this->build($ids, $truncated);

        $slugs = $religions->pluck('slug')->all();

        $graph['nodes'] = array_map(function (array $node) use ($slugs) {
            $others = array_column($node['otherReligions'], 'slug');

            $node['lane'] = in_array($node['religion']['slug'], $slugs, true)
                ? $node['religion']['slug']
                : collect($slugs)->first(fn (string $slug) => in_array($slug, $others, true));

            return $node;
        }, $graph['nodes']);

        return $graph + [
            'focus' => null,
            'lanes' => $religions->map(fn (Religion $religion) => $religion->reference())->values()->all(),
        ];
    }

    /**
     * Ids of the religions' members, primary or additional, in manual order.
     *
     * @param  list<int>  $religionIds
     * @return array{0: list<int>, 1: bool}
     */
    protected function membersOf(array $religionIds): array
    {
        $ids = Entity::query()->inReligion($religionIds)->ordered()->limit(self::MAX_NODES + 1)->pluck('id')->all();

        return [array_slice($ids, 0, self::MAX_NODES), count($ids) > self::MAX_NODES];
    }

    /**
     * @param  list<int>  $ids
     * @return array{nodes: list<array<string, mixed>>, edges: list<array<string, mixed>>, truncated: bool}
     */
    protected function build(array $ids, bool $truncated): array
    {
        $entities = Entity::query()
            ->with(['religion', 'additionalReligions', 'syncreticOrigin.religion'])
            ->whereIn('id', $ids)
            ->ordered()
            ->get();

        $edges = EntityRelation::query()
            ->with('tradition')
            ->whereIn('entity_id', $ids)
            ->whereIn('related_entity_id', $ids)
            ->get();

        return [
            'nodes' => $entities->map(fn (Entity $entity) => [
                'id' => $entity->id,
                'name' => $entity->name,
                'slug' => $entity->slug,
                'religion' => $entity->religion->reference(),
                'otherReligions' => $entity->additionalReligions->map(fn (Religion $religion) => $religion->reference())->all(),
                'gender' => $entity->gender ? [
                    'value' => $entity->gender->value,
                    'label' => $entity->gender->label(),
                    'symbol' => $entity->gender->symbol(),
                ] : null,
                'element' => $entity->element,
                'syncreticOrigin' => $entity->syncreticOrigin ? [
                    'id' => $entity->syncreticOrigin->id,
                    'name' => $entity->syncreticOrigin->name,
                    'religion' => $entity->syncreticOrigin->religion->name,
                    'url' => route('entities.show', $entity->syncreticOrigin),
                    'graphUrl' => route('graph.entities', $entity->syncreticOrigin),
                ] : null,
                'region' => $entity->region,
                'origin' => $entity->origin,
                'aliases' => $entity->aliases ?? [],
                'lane' => null,
                'url' => route('entities.show', $entity),
                'graphUrl' => route('graph.entities', $entity),
            ])->all(),
            'edges' => $edges->map(function (EntityRelation $relation) {
                [$from, $to] = $relation->displayOrder();

                return [
                    'id' => $relation->id,
                    'from' => $from,
                    'to' => $to,
                    'kind' => $relation->kind->value,
                    'tradition' => $relation->tradition?->name,
                    'note' => $relation->note,
                    'swappable' => $relation->hasSwappableSides(),
                ];
            })->all(),
            'truncated' => $truncated,
        ];
    }
}

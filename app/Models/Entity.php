<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\RelationRole;
use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\EntityFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

#[Fillable(['religion_id', 'name', 'gender', 'region', 'origin', 'aliases'])]
class Entity extends Model
{
    /** @use HasFactory<EntityFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * New entities go to the end of the admins' manual order.
     */
    protected static function booted(): void
    {
        static::creating(function (Entity $entity) {
            $entity->position = (int) static::query()->max('position') + 1;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'aliases' => 'json:unicode',
            'gender' => Gender::class,
        ];
    }

    /**
     * @return BelongsTo<Religion, $this>
     */
    public function religion(): BelongsTo
    {
        return $this->belongsTo(Religion::class);
    }

    /**
     * Religions the entity also belongs to besides its primary one.
     *
     * @return BelongsToMany<Religion, $this>
     */
    public function additionalReligions(): BelongsToMany
    {
        return $this->belongsToMany(Religion::class, 'entity_religion')->orderBy('name');
    }

    /**
     * Relations stored with this entity on the "entity_id" side.
     *
     * @return HasMany<EntityRelation, $this>
     */
    public function outgoingRelations(): HasMany
    {
        return $this->hasMany(EntityRelation::class);
    }

    /**
     * Relations stored with this entity on the "related_entity_id" side.
     *
     * @return HasMany<EntityRelation, $this>
     */
    public function incomingRelations(): HasMany
    {
        return $this->hasMany(EntityRelation::class, 'related_entity_id');
    }

    /**
     * Entities belonging to any of the religions, as primary or additional.
     *
     * @param  Builder<Entity>  $query
     * @param  int|list<int>  $religionIds
     */
    #[Scope]
    protected function inReligion(Builder $query, int|array $religionIds): void
    {
        $religionIds = (array) $religionIds;

        $query->where(function (Builder $query) use ($religionIds) {
            $query->whereIn('religion_id', $religionIds)
                ->orWhereHas('additionalReligions', fn (Builder $query) => $query->whereIn('religions.id', $religionIds));
        });
    }

    /**
     * The admins' manual order, then by name.
     *
     * @param  Builder<Entity>  $query
     */
    #[Scope]
    protected function ordered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('name');
    }

    /**
     * Puts the given entities in this order, reusing the positions they
     * already hold, so entities not in the list keep their place.
     *
     * @param  list<int>  $ids
     */
    public static function reorder(array $ids): void
    {
        DB::transaction(function () use ($ids) {
            $positions = static::query()->whereKey($ids)->orderBy('position')->pluck('position')->all();

            foreach (array_values($ids) as $index => $id) {
                static::query()->whereKey($id)->update(['position' => $positions[$index]]);
            }
        });
    }

    /**
     * Matches the term against names, aliases, regions and origins.
     *
     * @param  Builder<Entity>  $query
     */
    #[Scope]
    protected function search(Builder $query, string $term): void
    {
        $like = '%'.$term.'%';

        $query->where(function (Builder $query) use ($like) {
            $query->whereLike('name', $like)
                ->orWhereLike('aliases', $like)
                ->orWhereLike('region', $like)
                ->orWhereLike('origin', $like);
        });
    }

    /**
     * Eager loads everything relatives() needs.
     */
    public function loadRelatives(): static
    {
        return $this->load([
            'outgoingRelations.relatedEntity.religion',
            'outgoingRelations.tradition',
            'incomingRelations.entity.religion',
            'incomingRelations.tradition',
        ]);
    }

    /**
     * Every relation of this entity from its own point of view, sorted by
     * role and then by the other entity's name.
     *
     * @return Collection<int, array{role: RelationRole, entity: Entity, relation: EntityRelation}>
     */
    public function relatives(): Collection
    {
        $outgoing = $this->outgoingRelations->map(fn (EntityRelation $relation) => [
            'role' => $relation->roleFor($this),
            'entity' => $relation->relatedEntity,
            'relation' => $relation,
        ]);

        $incoming = $this->incomingRelations->map(fn (EntityRelation $relation) => [
            'role' => $relation->roleFor($this),
            'entity' => $relation->entity,
            'relation' => $relation,
        ]);

        return $outgoing->toBase()->concat($incoming)
            ->sortBy([
                fn (array $a, array $b) => $a['role']->sortOrder() <=> $b['role']->sortOrder(),
                fn (array $a, array $b) => strcasecmp($a['entity']->name, $b['entity']->name),
            ])
            ->values();
    }
}

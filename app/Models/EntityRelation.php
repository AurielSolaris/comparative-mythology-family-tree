<?php

namespace App\Models;

use App\Enums\RelationKind;
use App\Enums\RelationRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['entity_id', 'related_entity_id', 'kind', 'religion_id', 'note'])]
class EntityRelation extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'kind' => RelationKind::class,
            'sides_swapped' => 'boolean',
        ];
    }

    /**
     * Whether the tree lets admins choose which side each entity is drawn on
     * (partners and siblings sit side by side in the same generation).
     */
    public function hasSwappableSides(): bool
    {
        return in_array($this->kind, [RelationKind::Consort, RelationKind::Sibling], true);
    }

    /**
     * The two entity ids in the order the tree draws them, left to right.
     * Directional relations are always superior (parent, council head) first.
     *
     * @return array{0: int, 1: int}
     */
    public function displayOrder(): array
    {
        return $this->sides_swapped && $this->hasSwappableSides()
            ? [$this->related_entity_id, $this->entity_id]
            : [$this->entity_id, $this->related_entity_id];
    }

    public function swapSides(): void
    {
        $this->forceFill(['sides_swapped' => ! $this->sides_swapped])->save();
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function entity(): BelongsTo
    {
        return $this->belongsTo(Entity::class);
    }

    /**
     * @return BelongsTo<Entity, $this>
     */
    public function relatedEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'related_entity_id');
    }

    /**
     * The tradition this relation comes from, if recorded.
     *
     * @return BelongsTo<Religion, $this>
     */
    public function tradition(): BelongsTo
    {
        return $this->belongsTo(Religion::class, 'religion_id');
    }

    /**
     * The row attributes for "$entity <role> $other", normalized so each
     * relation is stored exactly once.
     *
     * @return array{entity_id: int, related_entity_id: int, kind: RelationKind}
     */
    public static function attributesFor(Entity $entity, RelationRole $role, Entity $other): array
    {
        [$from, $to] = match (true) {
            ! $role->kind()->isSymmetric() => $role->isSuperior() ? [$entity, $other] : [$other, $entity],
            default => $entity->id < $other->id ? [$entity, $other] : [$other, $entity],
        };

        return ['entity_id' => $from->id, 'related_entity_id' => $to->id, 'kind' => $role->kind()];
    }

    /**
     * The role this relation plays for the given entity.
     */
    public function roleFor(Entity $entity): RelationRole
    {
        $isSuperior = $this->entity_id === $entity->id;

        return match ($this->kind) {
            RelationKind::Parent => $isSuperior ? RelationRole::ParentOf : RelationRole::ChildOf,
            RelationKind::Council => $isSuperior ? RelationRole::CouncilHeadOf : RelationRole::CouncilMemberOf,
            RelationKind::Consort => RelationRole::ConsortOf,
            RelationKind::Sibling => RelationRole::SiblingOf,
            RelationKind::Equivalent => RelationRole::EquivalentOf,
        };
    }
}

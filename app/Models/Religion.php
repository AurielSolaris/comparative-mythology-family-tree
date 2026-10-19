<?php

namespace App\Models;

use App\Models\Concerns\HasUniqueSlug;
use Database\Factories\ReligionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'color', 'region', 'time_period'])]
class Religion extends Model
{
    /** @use HasFactory<ReligionFactory> */
    use HasFactory, HasUniqueSlug;

    /**
     * How the explorer refers to the religion. A null colour means the
     * canvas derives one from the slug.
     *
     * @return array{name: string, slug: string, color: string|null}
     */
    public function reference(): array
    {
        return ['name' => $this->name, 'slug' => $this->slug, 'color' => $this->color];
    }

    /**
     * Entities whose primary religion this is.
     *
     * @return HasMany<Entity, $this>
     */
    public function entities(): HasMany
    {
        return $this->hasMany(Entity::class);
    }

    /**
     * Entities that also belong to this religion besides their primary one.
     *
     * @return BelongsToMany<Entity, $this>
     */
    public function additionalEntities(): BelongsToMany
    {
        return $this->belongsToMany(Entity::class, 'entity_religion');
    }

    /**
     * Primary plus additional members. Needs withMemberCount() on the query.
     */
    public function memberCount(): int
    {
        return $this->entities_count + $this->additional_entities_count;
    }

    /**
     * @param  Builder<Religion>  $query
     */
    #[Scope]
    protected function withMemberCount(Builder $query): void
    {
        $query->withCount(['entities', 'additionalEntities']);
    }
}

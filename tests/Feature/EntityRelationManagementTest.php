<?php

namespace Tests\Feature;

use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class EntityRelationManagementTest extends TestCase
{
    use RefreshDatabase;

    private Entity $zeus;

    private Entity $apollo;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
        [$this->zeus, $this->apollo] = Entity::factory()->count(2)->sequence(['name' => 'Zeus'], ['name' => 'Apollo'])->create();
    }

    public function test_a_parent_relation_is_stored_from_parent_to_child(): void
    {
        $this->relate($this->apollo, 'child_of', $this->zeus, 'with Leto')
            ->assertRedirect(route('admin.entities.edit', $this->apollo).'#relations');

        $this->assertDatabaseHas('entity_relations', [
            'entity_id' => $this->zeus->id,
            'related_entity_id' => $this->apollo->id,
            'kind' => RelationKind::Parent->value,
            'note' => 'with Leto',
        ]);
    }

    public function test_symmetric_relations_are_stored_once_whichever_side_adds_them(): void
    {
        $this->relate($this->apollo, 'sibling_of', $this->zeus)->assertSessionHasNoErrors();
        $this->relate($this->zeus, 'sibling_of', $this->apollo)->assertSessionHasErrors('related_entity_id');

        $this->assertDatabaseHas('entity_relations', [
            'entity_id' => $this->zeus->id,
            'related_entity_id' => $this->apollo->id,
            'kind' => RelationKind::Sibling->value,
        ]);
        $this->assertDatabaseCount('entity_relations', 1);
    }

    public function test_co_rulers_are_symmetric_and_shown_from_both_sides(): void
    {
        $this->relate($this->apollo, 'co_ruler_of', $this->zeus)->assertSessionHasNoErrors();
        $this->relate($this->zeus, 'co_ruler_of', $this->apollo)->assertSessionHasErrors('related_entity_id');

        $relation = EntityRelation::query()->sole();
        $this->assertSame(RelationKind::CoRuler, $relation->kind);
        $this->assertSame([$this->zeus->id, $this->apollo->id], [$relation->entity_id, $relation->related_entity_id]);
        $this->assertTrue($relation->hasSwappableSides());

        $this->get(route('admin.entities.edit', $this->zeus))->assertSee('Co-ruler with')->assertSee('Apollo');
        $this->getJson(route('graph.entities', $this->apollo))
            ->assertJsonFragment(['kind' => 'co_ruler', 'swappable' => true]);
    }

    public function test_the_reverse_of_an_existing_parent_relation_is_rejected(): void
    {
        $this->relate($this->zeus, 'parent_of', $this->apollo)->assertSessionHasNoErrors();

        $this->relate($this->zeus, 'child_of', $this->apollo)->assertSessionHasErrors('related_entity_id');
    }

    public function test_an_entity_cannot_be_related_to_itself(): void
    {
        $this->relate($this->zeus, 'consort_of', $this->zeus)->assertSessionHasErrors('related_entity_id');
    }

    public function test_the_role_must_be_valid(): void
    {
        $this->relate($this->zeus, 'cousin_of', $this->apollo)->assertSessionHasErrors('role');
    }

    public function test_a_relation_can_be_removed(): void
    {
        $relation = EntityRelation::create([
            'entity_id' => $this->zeus->id,
            'related_entity_id' => $this->apollo->id,
            'kind' => RelationKind::Consort,
        ]);

        $this->delete(route('admin.relations.destroy', $relation))->assertRedirect();

        $this->assertModelMissing($relation);
    }

    public function test_partners_can_switch_sides(): void
    {
        $relation = EntityRelation::create([
            'entity_id' => $this->zeus->id,
            'related_entity_id' => $this->apollo->id,
            'kind' => RelationKind::Consort,
        ]);

        $this->patchJson(route('admin.relations.sides', $relation))
            ->assertOk()
            ->assertJson(['from' => $this->apollo->id, 'to' => $this->zeus->id]);

        $this->patchJson(route('admin.relations.sides', $relation))
            ->assertJson(['from' => $this->zeus->id, 'to' => $this->apollo->id]);
    }

    public function test_parent_relations_have_no_sides_to_switch(): void
    {
        $relation = EntityRelation::create([
            'entity_id' => $this->zeus->id,
            'related_entity_id' => $this->apollo->id,
            'kind' => RelationKind::Parent,
        ]);

        $this->patchJson(route('admin.relations.sides', $relation))->assertUnprocessable();
        $this->assertFalse($relation->fresh()->sides_swapped);
    }

    public function test_visitors_cannot_switch_sides(): void
    {
        $relation = EntityRelation::create([
            'entity_id' => $this->zeus->id,
            'related_entity_id' => $this->apollo->id,
            'kind' => RelationKind::Sibling,
        ]);

        auth()->logout();

        $this->patchJson(route('admin.relations.sides', $relation))->assertUnauthorized();
        $this->assertFalse($relation->fresh()->sides_swapped);
    }

    private function relate(Entity $entity, string $role, Entity $other, ?string $note = null): TestResponse
    {
        return $this->post(route('admin.entities.relations.store', $entity), [
            'role' => $role,
            'related_entity_id' => $other->id,
            'note' => $note,
        ]);
    }
}

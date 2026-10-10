<?php

namespace Tests\Feature;

use App\Enums\Gender;
use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->admin()->create());
    }

    public function test_an_entity_can_be_created_with_aliases_one_per_line(): void
    {
        $religion = Religion::factory()->create(['name' => 'Norse']);

        $response = $this->post(route('admin.entities.store'), [
            'religion_id' => $religion->id,
            'name' => 'Odin',
            'gender' => 'male',
            'region' => 'Scandinavia',
            'origin' => 'Son of Borr and Bestla',
            'aliases' => "Óðinn\r\n  Wodan \n\nAllfather",
        ]);

        $entity = Entity::query()->sole();

        $response->assertRedirect(route('admin.entities.edit', $entity));
        $this->assertSame(['Óðinn', 'Wodan', 'Allfather'], $entity->aliases);
        $this->assertSame('odin', $entity->slug);
        $this->assertSame('Scandinavia', $entity->region);
        $this->assertSame(Gender::Male, $entity->gender);
    }

    public function test_gender_is_optional_but_must_be_a_known_value(): void
    {
        $religion = Religion::factory()->create();

        $this->post(route('admin.entities.store'), ['religion_id' => $religion->id, 'name' => 'Ymir', 'gender' => ''])
            ->assertSessionHasNoErrors();
        $this->assertNull(Entity::query()->sole()->gender);

        $this->post(route('admin.entities.store'), ['religion_id' => $religion->id, 'name' => 'Loki', 'gender' => 'sometimes'])
            ->assertSessionHasErrors('gender');
    }

    public function test_names_are_unique_within_a_religion_but_not_across_religions(): void
    {
        $greek = Religion::factory()->create();
        $roman = Religion::factory()->create();
        Entity::factory()->for($greek)->create(['name' => 'Apollo']);

        $this->post(route('admin.entities.store'), ['religion_id' => $greek->id, 'name' => 'Apollo'])
            ->assertSessionHasErrors('name');

        $this->post(route('admin.entities.store'), ['religion_id' => $roman->id, 'name' => 'Apollo'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['apollo', 'apollo-2'], Entity::query()->orderBy('id')->pluck('slug')->all());
    }

    public function test_the_religion_is_required(): void
    {
        $this->post(route('admin.entities.store'), ['name' => 'Nobody'])->assertSessionHasErrors('religion_id');
    }

    public function test_an_entity_can_be_updated(): void
    {
        $entity = Entity::factory()->create();

        $this->put(route('admin.entities.update', $entity), [
            'religion_id' => $entity->religion_id,
            'name' => 'Renamed',
            'aliases' => '',
        ])->assertRedirect(route('admin.entities.edit', $entity));

        $entity->refresh();
        $this->assertSame('Renamed', $entity->name);
        $this->assertSame([], $entity->aliases);
    }

    public function test_deleting_an_entity_removes_its_relations(): void
    {
        [$parent, $child] = Entity::factory()->count(2)->create();
        EntityRelation::create(['entity_id' => $parent->id, 'related_entity_id' => $child->id, 'kind' => RelationKind::Parent]);

        $this->delete(route('admin.entities.destroy', $parent))->assertRedirect(route('admin.entities.index'));

        $this->assertModelMissing($parent);
        $this->assertDatabaseCount('entity_relations', 0);
    }

    public function test_the_index_can_be_filtered(): void
    {
        $norse = Religion::factory()->create();
        Entity::factory()->for($norse)->create(['name' => 'Thor', 'aliases' => ['Donar']]);
        Entity::factory()->create(['name' => 'Zeus']);

        $this->get(route('admin.entities.index', ['q' => 'donar']))->assertSee('Thor')->assertDontSee('Zeus');
        $this->get(route('admin.entities.index', ['religion' => $norse->id]))->assertSee('Thor')->assertDontSee('Zeus');
    }

    public function test_the_index_can_be_filtered_by_gender(): void
    {
        Entity::factory()->create(['name' => 'Freyja', 'gender' => Gender::Female]);
        Entity::factory()->create(['name' => 'Freyr', 'gender' => Gender::Male]);

        $this->get(route('admin.entities.index', ['gender' => 'female']))->assertSee('Freyja')->assertDontSee('Freyr');
    }

    public function test_new_entities_go_to_the_end_of_the_order(): void
    {
        [$first, $second] = Entity::factory()->count(2)->create();

        $this->assertGreaterThan($first->position, $second->position);
    }

    public function test_dragged_rows_are_saved_in_their_new_order(): void
    {
        [$a, $b, $c] = Entity::factory()->count(3)->sequence(['name' => 'A'], ['name' => 'B'], ['name' => 'C'])->create();

        $this->patchJson(route('admin.entities.reorder'), ['ids' => [$c->id, $a->id, $b->id]])->assertOk();

        $this->get(route('admin.entities.index'))->assertSeeInOrder(['>C<', '>A<', '>B<'], false);
    }

    public function test_reordering_a_subset_keeps_the_other_entities_in_place(): void
    {
        [$a, $b, $c, $d] = Entity::factory()->count(4)->create();

        // Swap B and D, e.g. while the list is filtered to just those two.
        $this->patchJson(route('admin.entities.reorder'), ['ids' => [$d->id, $b->id]])->assertOk();

        $this->assertSame(
            [$a->id, $d->id, $c->id, $b->id],
            Entity::query()->ordered()->pluck('id')->all(),
        );
    }

    public function test_reordering_requires_existing_distinct_entities(): void
    {
        $entity = Entity::factory()->create();

        $this->patchJson(route('admin.entities.reorder'), ['ids' => [$entity->id, $entity->id]])->assertJsonValidationErrors('ids.1');
        $this->patchJson(route('admin.entities.reorder'), ['ids' => [999]])->assertJsonValidationErrors('ids.0');
    }

    public function test_visitors_cannot_reorder(): void
    {
        $entity = Entity::factory()->create();
        auth()->logout();

        $this->patchJson(route('admin.entities.reorder'), ['ids' => [$entity->id]])->assertUnauthorized();
    }

    public function test_the_edit_page_lists_relations(): void
    {
        [$zeus, $apollo] = Entity::factory()->count(2)
            ->sequence(['name' => 'Zeus', 'gender' => Gender::Male], ['name' => 'Apollo', 'gender' => null])
            ->create();
        EntityRelation::create(['entity_id' => $zeus->id, 'related_entity_id' => $apollo->id, 'kind' => RelationKind::Parent, 'note' => 'with Leto']);

        $this->get(route('admin.entities.edit', $apollo))
            ->assertOk()
            ->assertSeeInOrder(['Child of', 'Zeus', 'with Leto']);

        $this->get(route('admin.entities.edit', $zeus))
            ->assertSeeInOrder(['Father of', 'Apollo']);
    }

    public function test_relation_words_follow_the_entitys_gender(): void
    {
        [$el, $athirat, $anat] = Entity::factory()->count(3)->sequence(
            ['name' => 'El', 'gender' => Gender::Male],
            ['name' => 'Athirat', 'gender' => Gender::Female],
            ['name' => 'Anat', 'gender' => Gender::Female],
        )->create();
        EntityRelation::create(['entity_id' => $el->id, 'related_entity_id' => $athirat->id, 'kind' => RelationKind::Consort]);
        EntityRelation::create(['entity_id' => $el->id, 'related_entity_id' => $anat->id, 'kind' => RelationKind::Parent]);

        $this->get(route('admin.entities.edit', $el))->assertSee('Husband of')->assertSee('Father of');
        $this->get(route('admin.entities.edit', $athirat))->assertSee('Wife of');
        $this->get(route('admin.entities.edit', $anat))->assertSee('Daughter of');
    }
}

<?php

namespace Tests\Feature;

use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Entities shared between pantheons (Baal: Canaanite, also Eblaite),
 * relations recorded per tradition, councils, and side-by-side comparison.
 */
class CrossPantheonTest extends TestCase
{
    use RefreshDatabase;

    private Religion $canaanite;

    private Religion $eblaite;

    private Religion $greek;

    private Entity $el;

    private Entity $dagan;

    private Entity $baal;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->canaanite, $this->eblaite, $this->greek] = Religion::factory()->count(3)
            ->sequence(['name' => 'Canaanite'], ['name' => 'Eblaite'], ['name' => 'Greek'])
            ->create();

        $this->el = Entity::factory()->for($this->canaanite)->create(['name' => 'El']);
        $this->dagan = Entity::factory()->for($this->eblaite)->create(['name' => 'Dagan']);
        $this->baal = Entity::factory()->for($this->canaanite)->create(['name' => 'Baal']);
        $this->baal->additionalReligions()->attach($this->eblaite);
    }

    public function test_admins_can_give_an_entity_additional_religions(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.entities.update', $this->el), [
            'religion_id' => $this->canaanite->id,
            'name' => 'El',
            // The primary religion is dropped even if ticked.
            'additional_religion_ids' => [$this->eblaite->id, $this->canaanite->id],
        ])->assertSessionHasNoErrors();

        $this->assertSame([$this->eblaite->id], $this->el->additionalReligions()->pluck('religions.id')->all());

        $this->put(route('admin.entities.update', $this->el), [
            'religion_id' => $this->canaanite->id,
            'name' => 'El',
        ])->assertSessionHasNoErrors();

        $this->assertCount(0, $this->el->additionalReligions()->get());
    }

    public function test_the_admin_religion_filter_includes_additional_members(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->get(route('admin.entities.index', ['religion' => $this->eblaite->id]))
            ->assertSee('Dagan')
            ->assertSee('Baal')
            ->assertDontSee('>El<', false);
    }

    public function test_council_relations_point_from_the_head_to_the_member_and_record_their_tradition(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->post(route('admin.entities.relations.store', $this->baal), [
            'role' => 'council_member_of',
            'related_entity_id' => $this->el->id,
            'religion_id' => $this->canaanite->id,
        ])->assertSessionHasNoErrors();

        $this->assertDatabaseHas('entity_relations', [
            'entity_id' => $this->el->id,
            'related_entity_id' => $this->baal->id,
            'kind' => RelationKind::Council->value,
            'religion_id' => $this->canaanite->id,
        ]);

        $this->post(route('admin.entities.relations.store', $this->baal), [
            'role' => 'council_head_of',
            'related_entity_id' => $this->el->id,
        ])->assertSessionHasErrors('related_entity_id');

        $this->get(route('admin.entities.edit', $this->baal))
            ->assertSeeInOrder(['Member of the council/court of', 'El', 'Canaanite tradition']);
    }

    public function test_a_religion_graph_includes_additional_members(): void
    {
        $names = array_column($this->getJson(route('graph.religions', $this->eblaite))->json('nodes'), 'name');

        $this->assertEqualsCanonicalizing(['Baal', 'Dagan'], $names);
    }

    public function test_religions_can_be_compared_side_by_side(): void
    {
        $this->relate($this->dagan, $this->baal, RelationKind::Parent, $this->eblaite);
        $this->relate($this->el, $this->baal, RelationKind::Council, $this->canaanite);
        Entity::factory()->for($this->greek)->create(['name' => 'Zeus']);

        $response = $this->getJson(route('graph.compare', ['religions' => 'eblaite,canaanite']))->assertOk();

        $response->assertJsonPath('lanes', [
            ['name' => 'Eblaite', 'slug' => 'eblaite', 'color' => null],
            ['name' => 'Canaanite', 'slug' => 'canaanite', 'color' => null],
        ]);

        $lanes = collect($response->json('nodes'))->pluck('lane', 'name')->all();
        // Baal's primary religion is selected, so he sits in its lane.
        $this->assertEquals(['El' => 'canaanite', 'Baal' => 'canaanite', 'Dagan' => 'eblaite'], $lanes);

        $response->assertJsonFragment(['from' => $this->dagan->id, 'to' => $this->baal->id, 'kind' => 'parent', 'tradition' => 'Eblaite']);
        $response->assertJsonFragment(['from' => $this->el->id, 'to' => $this->baal->id, 'kind' => 'council', 'tradition' => 'Canaanite']);

        $this->get(route('compare', ['religions' => 'eblaite,canaanite']))
            ->assertOk()
            ->assertViewHas('selectedReligions', ['eblaite', 'canaanite']);
    }

    public function test_an_additional_member_sits_in_the_first_selected_religion_it_belongs_to(): void
    {
        $lanes = collect($this->getJson(route('graph.compare', ['religions' => 'eblaite,greek']))->json('nodes'))
            ->pluck('lane', 'name')
            ->all();

        $this->assertEquals(['Dagan' => 'eblaite', 'Baal' => 'eblaite'], $lanes);
    }

    public function test_a_comparison_needs_two_to_four_existing_religions(): void
    {
        $this->getJson(route('graph.compare', ['religions' => 'eblaite']))->assertJsonValidationErrors('religions');
        $this->getJson(route('graph.compare', ['religions' => 'eblaite,atlantean']))->assertJsonValidationErrors('religions');
        $this->getJson(route('graph.compare'))->assertJsonValidationErrors('religions');
    }

    public function test_a_religion_that_is_only_an_additional_one_can_be_deleted(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $phoenician = Religion::factory()->create();
        $this->el->additionalReligions()->attach($phoenician);

        $this->delete(route('admin.religions.destroy', $phoenician))->assertRedirect(route('admin.religions.index'));

        $this->assertModelMissing($phoenician);
        $this->assertCount(0, $this->el->additionalReligions()->get());
    }

    private function relate(Entity $from, Entity $to, RelationKind $kind, ?Religion $tradition = null): void
    {
        EntityRelation::create([
            'entity_id' => $from->id,
            'related_entity_id' => $to->id,
            'kind' => $kind,
            'religion_id' => $tradition?->id,
        ]);
    }
}

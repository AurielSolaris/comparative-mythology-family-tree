<?php

namespace Tests\Feature;

use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExplorerTest extends TestCase
{
    use RefreshDatabase;

    private Religion $greek;

    private Entity $cronus;

    private Entity $zeus;

    private Entity $hera;

    private Entity $apollo;

    private Entity $jupiter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->greek = Religion::factory()->create(['name' => 'Greek']);
        $roman = Religion::factory()->create(['name' => 'Roman']);

        [$this->cronus, $this->zeus, $this->hera, $this->apollo] = Entity::factory()->for($this->greek)->count(4)
            ->sequence(['name' => 'Cronus'], ['name' => 'Zeus', 'aliases' => ['Ζεύς']], ['name' => 'Hera'], ['name' => 'Apollo'])
            ->create();
        $this->jupiter = Entity::factory()->for($roman)->create(['name' => 'Jupiter']);

        $this->relate($this->cronus, $this->zeus, RelationKind::Parent);
        $this->relate($this->zeus, $this->apollo, RelationKind::Parent);
        $this->relate($this->zeus, $this->hera, RelationKind::Consort);
        $this->relate($this->zeus, $this->jupiter, RelationKind::Equivalent);
    }

    public function test_the_explorer_pages_render(): void
    {
        $this->get(route('explore'))->assertOk()->assertSee('Explore the family trees of myth');
        $this->get(route('entities.show', $this->zeus))->assertOk()->assertViewHas('graphUrl', route('graph.entities', $this->zeus));
        $this->get(route('religions.show', $this->greek))->assertOk();
        $this->get('/entities/no-such-entity')->assertNotFound();
    }

    public function test_the_entity_graph_contains_the_neighbourhood_up_to_the_requested_depth(): void
    {
        $response = $this->getJson(route('graph.entities', ['entity' => $this->apollo, 'depth' => 1]))->assertOk();

        $response->assertJsonPath('focus', $this->apollo->id);
        $this->assertEqualsCanonicalizing(['Apollo', 'Zeus'], array_column($response->json('nodes'), 'name'));

        $response = $this->getJson(route('graph.entities', ['entity' => $this->apollo, 'depth' => 2]))->assertOk();

        $this->assertEqualsCanonicalizing(['Apollo', 'Cronus', 'Hera', 'Jupiter', 'Zeus'], array_column($response->json('nodes'), 'name'));
        $response->assertJsonFragment(['from' => $this->zeus->id, 'to' => $this->apollo->id, 'kind' => 'parent', 'note' => null]);
    }

    public function test_switched_partners_are_sent_in_their_display_order(): void
    {
        $relation = EntityRelation::query()->where('kind', RelationKind::Consort)->sole();
        $relation->swapSides();

        $this->getJson(route('graph.entities', $this->zeus))
            ->assertJsonFragment([
                'id' => $relation->id,
                'from' => $this->hera->id,
                'to' => $this->zeus->id,
                'kind' => 'consort',
                'swappable' => true,
            ]);
    }

    public function test_nodes_include_gender_and_suggestions_can_be_filtered_by_it(): void
    {
        Entity::query()->whereKeyNot($this->hera->id)->update(['gender' => 'male']);
        $this->hera->update(['gender' => 'female']);

        $this->getJson(route('graph.entities', $this->zeus))
            ->assertJsonFragment(['gender' => ['value' => 'male', 'label' => 'Male', 'symbol' => '♂']]);

        $this->getJson(route('search.suggestions', ['q' => 'e', 'gender' => 'female']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Hera')
            ->assertJsonPath('data.0.gender', 'Female');
    }

    public function test_the_depth_is_limited(): void
    {
        $this->getJson(route('graph.entities', ['entity' => $this->zeus, 'depth' => 9]))->assertUnprocessable();
    }

    public function test_the_religion_graph_includes_equivalents_from_other_religions(): void
    {
        $response = $this->getJson(route('graph.religions', $this->greek))->assertOk();

        $response->assertJsonPath('focus', null);
        $this->assertEqualsCanonicalizing(
            ['Apollo', 'Cronus', 'Hera', 'Jupiter', 'Zeus'],
            array_column($response->json('nodes'), 'name'),
        );
        $this->assertCount(4, $response->json('edges'));
    }

    public function test_suggestions_match_names_and_unicode_aliases(): void
    {
        $this->getJson(route('search.suggestions', ['q' => 'jup']))
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Jupiter')
            ->assertJsonPath('data.0.religion', 'Roman');

        $this->getJson(route('search.suggestions', ['q' => 'Ζεύ']))
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Zeus');

        $this->getJson(route('search.suggestions'))->assertUnprocessable();
    }

    private function relate(Entity $from, Entity $to, RelationKind $kind): void
    {
        EntityRelation::create(['entity_id' => $from->id, 'related_entity_id' => $to->id, 'kind' => $kind]);
    }
}

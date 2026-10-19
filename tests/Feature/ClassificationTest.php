<?php

namespace Tests\Feature;

use App\Enums\Classification;
use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

/**
 * What an entity is in each of its traditions: Indra is a God (deva) in
 * the Veda and a demon (daēva) in the Avesta.
 */
class ClassificationTest extends TestCase
{
    use RefreshDatabase;

    private Religion $vedic;

    private Religion $zoroastrian;

    private Entity $indra;

    protected function setUp(): void
    {
        parent::setUp();

        [$this->vedic, $this->zoroastrian] = Religion::factory()->count(2)
            ->sequence(['name' => 'Vedic'], ['name' => 'Zoroastrian'])
            ->create();

        $this->indra = Entity::factory()->for($this->vedic)->create([
            'name' => 'Indra', 'classification' => Classification::God, 'classification_term' => 'deva',
        ]);
        $this->indra->additionalReligions()->attach($this->zoroastrian, ['classification' => 'demon', 'classification_term' => 'daēva']);
    }

    public function test_admins_record_what_an_entity_is_in_each_religion(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $greek = Religion::factory()->create(['name' => 'Greek']);

        $this->put(route('admin.entities.update', $this->indra), [
            'name' => 'Indra',
            'religion_id' => $this->vedic->id,
            'classification' => 'god',
            'classification_term' => 'deva',
            'additional_religion_ids' => [$this->zoroastrian->id],
            'memberships' => [
                $this->zoroastrian->id => ['classification' => 'demon', 'term' => 'daēva'],
                // Details for a religion that is not ticked are ignored.
                $greek->id => ['classification' => 'god', 'term' => 'theos'],
            ],
        ])->assertSessionHasNoErrors();

        $indra = $this->indra->fresh();
        $this->assertSame(Classification::God, $indra->classification);
        $this->assertSame([$this->zoroastrian->id], $indra->additionalReligions->modelKeys());
        $this->assertSame(Classification::Demon, $indra->additionalReligions->first()->pivot->classification);
        $this->assertSame('daēva', $indra->additionalReligions->first()->pivot->classification_term);

        $this->put(route('admin.entities.update', $this->indra), [
            'name' => 'Indra',
            'religion_id' => $this->vedic->id,
            'classification' => '',
            'additional_religion_ids' => [$this->zoroastrian->id],
            'memberships' => [$this->zoroastrian->id => ['classification' => '', 'term' => '']],
        ])->assertSessionHasNoErrors();

        $indra = $this->indra->fresh();
        $this->assertNull($indra->classification);
        $this->assertNull($indra->additionalReligions->first()->pivot->classification);
        $this->assertNull($indra->additionalReligions->first()->pivot->classification_term);
    }

    public function test_classifications_must_be_known_values(): void
    {
        $this->actingAs(User::factory()->admin()->create());

        $this->put(route('admin.entities.update', $this->indra), [
            'name' => 'Indra',
            'religion_id' => $this->vedic->id,
            'classification' => 'monster',
            'additional_religion_ids' => [$this->zoroastrian->id],
            'memberships' => [$this->zoroastrian->id => ['classification' => 'fiend', 'term' => str_repeat('a', 256)]],
        ])->assertSessionHasErrors(['classification', "memberships.{$this->zoroastrian->id}.classification", "memberships.{$this->zoroastrian->id}.term"]);
    }

    public function test_the_explorer_shows_what_an_entity_is_in_each_religion(): void
    {
        $node = $this->getJson(route('graph.entities', $this->indra))->assertOk()->json('nodes.0');

        $this->assertSame(['value' => 'god', 'label' => 'God'], $node['religion']['classification']);
        $this->assertSame('deva', $node['religion']['classificationTerm']);
        $this->assertSame('Zoroastrian', $node['otherReligions'][0]['name']);
        $this->assertSame(['value' => 'demon', 'label' => 'Demon'], $node['otherReligions'][0]['classification']);
        $this->assertSame('daēva', $node['otherReligions'][0]['classificationTerm']);
    }

    public function test_the_export_carries_each_classification(): void
    {
        $entity = collect(json_decode($this->get(route('export', 'json'))->streamedContent(), true)['entities'])->firstWhere('name', 'Indra');

        $this->assertSame(['god', 'deva'], [$entity['classification'], $entity['classification_term']]);
        $this->assertSame(['zoroastrian'], $entity['also_religions']);
        $this->assertSame([['religion' => 'zoroastrian', 'classification' => 'demon', 'classification_term' => 'daēva']], $entity['memberships']);

        /** @var BinaryFileResponse $file */
        $file = $this->get(route('export', 'sqlite'))->baseResponse;
        $copy = tempnam(sys_get_temp_dir(), 'export-test-');
        copy($file->getFile()->getPathname(), $copy);
        $database = new PDO('sqlite:'.$copy);

        $this->assertSame(['god', 'deva'], $database->query("SELECT classification, classification_term FROM entities WHERE name = 'Indra'")->fetch(PDO::FETCH_NUM));
        $this->assertSame(['demon', 'daēva'], $database->query('SELECT classification, classification_term FROM entity_religions')->fetch(PDO::FETCH_NUM));

        $database = null;
        unlink($copy);
    }

    public function test_a_name_derivation_points_from_the_name_s_source(): void
    {
        $aeshma = Entity::factory()->for($this->zoroastrian)->create(['name' => 'Aeshma', 'gender' => 'male']);
        $asmodeus = Entity::factory()->for($this->zoroastrian)->create(['name' => 'Asmodeus', 'gender' => 'male']);
        $relation = EntityRelation::create(['entity_id' => $aeshma->id, 'related_entity_id' => $asmodeus->id, 'kind' => RelationKind::NameDerivation]);

        $this->assertSame('Gave its name to', $relation->roleFor($aeshma)->label());
        $this->assertSame('Name derived from', $relation->roleFor($asmodeus)->label());
        $this->assertTrue(RelationKind::Polemic->isSymmetric());
        $this->assertTrue(RelationKind::Polemic->isSyncretic());
    }
}

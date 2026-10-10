<?php

namespace Tests\Feature;

use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Source;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_sources_are_stored_against_entities_and_relations_by_morph_alias(): void
    {
        $el = Entity::factory()->create();
        $relation = EntityRelation::create(['entity_id' => $el->id, 'related_entity_id' => Entity::factory()->create()->id, 'kind' => RelationKind::Parent]);

        $entitySource = Source::factory()->for($el, 'sourceable')->create();
        $relationSource = Source::factory()->for($relation, 'sourceable')->create();

        $this->assertSame('entity', $entitySource->sourceable_type);
        $this->assertSame('entity_relation', $relationSource->sourceable_type);
        $this->assertTrue($el->sources()->sole()->is($entitySource));
        $this->assertTrue($relation->sources()->sole()->is($relationSource));
    }

    public function test_deleting_a_file_source_deletes_its_stored_file(): void
    {
        Storage::fake(Source::DISK);
        $source = Source::factory()->file()->create();

        Storage::disk(Source::DISK)->assertExists($source->path);

        $source->delete();

        Storage::disk(Source::DISK)->assertMissing($source->path);
    }

    public function test_deleting_an_entity_deletes_the_sources_of_it_and_its_relations(): void
    {
        Storage::fake(Source::DISK);
        $el = Entity::factory()->create();
        $baal = Entity::factory()->create();
        $relation = EntityRelation::create(['entity_id' => $el->id, 'related_entity_id' => $baal->id, 'kind' => RelationKind::Parent]);

        $entityFile = Source::factory()->file()->for($baal, 'sourceable')->create();
        $relationFile = Source::factory()->file()->for($relation, 'sourceable')->create();
        $unrelated = Source::factory()->for($el, 'sourceable')->create();

        $baal->delete();

        $this->assertModelMissing($entityFile);
        $this->assertModelMissing($relationFile);
        $this->assertModelExists($unrelated);
        Storage::disk(Source::DISK)->assertMissing([$entityFile->path, $relationFile->path]);
    }
}

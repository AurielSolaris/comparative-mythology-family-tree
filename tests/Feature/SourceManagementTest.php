<?php

namespace Tests\Feature;

use App\Enums\RelationKind;
use App\Enums\SourceKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class SourceManagementTest extends TestCase
{
    use RefreshDatabase;

    private Entity $el;

    private EntityRelation $relation;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake(Source::DISK);
        $this->actingAs(User::factory()->admin()->create());

        $this->el = Entity::factory()->create(['name' => 'El']);
        $this->relation = EntityRelation::create([
            'entity_id' => $this->el->id,
            'related_entity_id' => Entity::factory()->create(['name' => 'Anat'])->id,
            'kind' => RelationKind::Parent,
        ]);
    }

    public function test_a_link_source_can_be_added_to_an_entity(): void
    {
        $this->post(route('admin.entities.sources.store', $this->el), [
            'kind' => 'link', 'url' => 'https://www.britannica.com/topic/El', 'ref' => 'KTU 1.4 IV 24',
        ])->assertRedirect(route('admin.entities.edit', $this->el).'#sources');

        $source = $this->el->sources()->sole();
        $this->assertSame(SourceKind::Link, $source->kind);
        $this->assertSame('https://www.britannica.com/topic/El', $source->url);
        $this->assertSame('KTU 1.4 IV 24', $source->ref);

        $this->get(route('admin.entities.edit', $this->el))
            ->assertSee('britannica.com/topic/El')
            ->assertSee('KTU 1.4 IV 24');
    }

    public function test_a_file_source_can_be_added_to_a_relation_and_downloaded(): void
    {
        $this->post(route('admin.relations.sources.store', $this->relation), [
            'kind' => 'file', 'file' => UploadedFile::fake()->create('Baal Cycle.pdf', 200, 'application/pdf'), 'ref' => 'col. ii',
        ])->assertRedirect(route('admin.relations.sources', $this->relation));

        $source = $this->relation->sources()->sole();
        $this->assertSame(SourceKind::File, $source->kind);
        $this->assertSame('Baal Cycle.pdf', $source->original_name);
        Storage::disk(Source::DISK)->assertExists($source->path);

        $this->get(route('admin.relations.sources', $this->relation))->assertOk()->assertSee('Baal Cycle.pdf')->assertSee('col. ii');
        $this->get(route('admin.entities.edit', $this->el))->assertSee('1 source');

        $this->get(route('admin.sources.file', $source))
            ->assertOk()
            ->assertDownload('Baal Cycle.pdf');
    }

    public function test_sources_are_validated(): void
    {
        $this->post(route('admin.entities.sources.store', $this->el), ['kind' => 'link', 'url' => ''])
            ->assertSessionHasErrors(['url' => 'Enter the link to the source.']);
        $this->post(route('admin.entities.sources.store', $this->el), ['kind' => 'link', 'url' => 'javascript:alert(1)'])
            ->assertSessionHasErrors('url');
        $this->post(route('admin.entities.sources.store', $this->el), ['kind' => 'file'])
            ->assertSessionHasErrors(['file' => 'Choose a file to upload.']);
        $this->post(route('admin.entities.sources.store', $this->el), ['kind' => 'file', 'file' => UploadedFile::fake()->create('script.php', 1)])
            ->assertSessionHasErrors('file');
        $this->post(route('admin.entities.sources.store', $this->el), ['kind' => 'scroll'])
            ->assertSessionHasErrors('kind');

        $this->assertSame(0, Source::query()->count());
    }

    public function test_a_link_source_can_be_edited(): void
    {
        $source = Source::factory()->for($this->el, 'sourceable')->create();

        $this->get(route('admin.sources.edit', $source))->assertOk()->assertSee($source->url);

        $this->put(route('admin.sources.update', $source), ['url' => 'https://etcsl.orinst.ox.ac.uk/section1/tr141.htm', 'ref' => 'lines 1–5'])
            ->assertRedirect(route('admin.entities.edit', $this->el).'#sources');

        $this->assertSame('https://etcsl.orinst.ox.ac.uk/section1/tr141.htm', $source->fresh()->url);
        $this->assertSame('lines 1–5', $source->fresh()->ref);
    }

    public function test_a_file_source_keeps_its_file_or_has_it_replaced(): void
    {
        $source = Source::factory()->file('old.txt')->for($this->relation, 'sourceable')->create();
        $oldPath = $source->path;

        $this->put(route('admin.sources.update', $source), ['ref' => 'new ref'])
            ->assertRedirect(route('admin.relations.sources', $this->relation));
        $this->assertSame($oldPath, $source->fresh()->path);
        $this->assertSame('new ref', $source->fresh()->ref);

        $this->put(route('admin.sources.update', $source), ['file' => UploadedFile::fake()->createWithContent('new.txt', 'tablet')]);

        $source->refresh();
        $this->assertSame('new.txt', $source->original_name);
        Storage::disk(Source::DISK)->assertExists($source->path);
        Storage::disk(Source::DISK)->assertMissing($oldPath);
    }

    public function test_a_source_can_be_removed(): void
    {
        $source = Source::factory()->file()->for($this->el, 'sourceable')->create();

        $this->delete(route('admin.sources.destroy', $source))
            ->assertRedirect(route('admin.entities.edit', $this->el).'#sources');

        $this->assertModelMissing($source);
        Storage::disk(Source::DISK)->assertMissing($source->path);
    }

    public function test_only_admins_can_manage_or_download_sources(): void
    {
        $source = Source::factory()->file()->for($this->el, 'sourceable')->create();

        $this->actingAs(User::factory()->create());

        $this->get(route('admin.sources.file', $source))->assertForbidden();
        $this->get(route('admin.relations.sources', $this->relation))->assertForbidden();
        $this->post(route('admin.entities.sources.store', $this->el), ['kind' => 'link', 'url' => 'https://example.com'])->assertForbidden();
        $this->delete(route('admin.sources.destroy', $source))->assertForbidden();

        $this->assertModelExists($source);
    }
}

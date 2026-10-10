<?php

namespace Tests\Feature;

use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

class DatasetExportTest extends TestCase
{
    use RefreshDatabase;

    private Religion $canaanite;

    private Entity $el;

    private Entity $baal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canaanite = Religion::factory()->create(['name' => 'Canaanite', 'color' => '#aa8800']);
        $eblaite = Religion::factory()->create(['name' => 'Eblaite']);

        $this->el = Entity::factory()->for($this->canaanite)->create(['name' => 'El', 'gender' => 'male', 'aliases' => ['Ilu', 'ʾĒl']]);
        $this->baal = Entity::factory()->for($this->canaanite)->create(['name' => 'Baal']);
        $this->baal->additionalReligions()->attach($eblaite);

        EntityRelation::create([
            'entity_id' => $this->el->id,
            'related_entity_id' => $this->baal->id,
            'kind' => RelationKind::Council,
            'religion_id' => $this->canaanite->id,
            'note' => 'divine assembly',
        ]);

        User::factory()->admin()->create(['email' => 'secret-admin@example.com']);
    }

    public function test_visitors_can_download_the_dataset_as_json(): void
    {
        $response = $this->get(route('export', 'json'))->assertOk();

        $this->assertStringContainsString('attachment; filename=comparative-mythology-', $response->headers->get('Content-Disposition'));
        $this->assertStringEndsWith('.json', $response->headers->get('Content-Disposition'));

        $content = $response->streamedContent();
        $data = json_decode($content, true, flags: JSON_THROW_ON_ERROR);

        $this->assertSame(2, $data['format_version']);
        $this->assertSame(['Canaanite', 'Eblaite'], array_column($data['religions'], 'name'));
        $this->assertSame('#aa8800', $data['religions'][0]['color']);

        $baal = collect($data['entities'])->firstWhere('name', 'Baal');
        $this->assertSame('canaanite', $baal['religion']);
        $this->assertSame(['eblaite'], $baal['also_religions']);
        $this->assertSame(['Ilu', 'ʾĒl'], collect($data['entities'])->firstWhere('name', 'El')['aliases']);

        $this->assertSame([[
            'kind' => 'council', 'from' => 'el', 'to' => 'baal',
            'from_role' => 'Council/court includes', 'to_role' => 'Member of the council/court of',
            'tradition' => 'canaanite', 'note' => 'divine assembly', 'sides_swapped' => false,
        ]], $data['relations']);

        // The app's database ids are not exported; slugs identify everything.
        foreach (['religions', 'entities', 'relations'] as $section) {
            foreach ($data[$section] as $record) {
                $this->assertArrayNotHasKey('id', $record);
            }
        }

        $this->assertStringNotContainsString('secret-admin@example.com', $content);
        $this->assertArrayNotHasKey('users', $data);
    }

    public function test_the_dataset_can_be_downloaded_as_a_sqlite_database(): void
    {
        foreach (['sqlite', 'db'] as $extension) {
            $response = $this->get(route('export', $extension))->assertOk()->assertDownload();

            $this->assertStringEndsWith(".{$extension}", $response->headers->get('Content-Disposition'));

            /** @var BinaryFileResponse $file */
            $file = $response->baseResponse;
            $copy = tempnam(sys_get_temp_dir(), 'export-test-');
            copy($file->getFile()->getPathname(), $copy);

            $database = new PDO('sqlite:'.$copy);

            $this->assertSame('comparative-mythology-family-tree', $database->query("SELECT value FROM meta WHERE key = 'format'")->fetchColumn());
            $this->assertSame(['Canaanite', 'Eblaite'], $database->query('SELECT name FROM religions ORDER BY name')->fetchAll(PDO::FETCH_COLUMN));
            $this->assertSame(['Baal', 'El'], $database->query('SELECT name FROM entities ORDER BY name')->fetchAll(PDO::FETCH_COLUMN));
            $this->assertSame('Eblaite', $database->query(
                'SELECT r.name FROM entity_religions er JOIN entities e ON e.id = er.entity_id JOIN religions r ON r.id = er.religion_id WHERE e.name = "Baal"',
            )->fetchColumn());
            $this->assertSame(['council', 'El', 'Baal', 'Canaanite'], $database->query(
                'SELECT rel.kind, a.name, b.name, t.name FROM relations rel
                 JOIN entities a ON a.id = rel.entity_id JOIN entities b ON b.id = rel.related_entity_id
                 JOIN religions t ON t.id = rel.tradition_religion_id',
            )->fetch(PDO::FETCH_NUM));
            $this->assertSame('["Ilu","ʾĒl"]', $database->query("SELECT aliases FROM entities WHERE name = 'El'")->fetchColumn());
            $this->assertSame([], $database->query('PRAGMA foreign_key_check')->fetchAll());
            $this->assertFalse((bool) $database->query("SELECT 1 FROM sqlite_master WHERE name = 'users'")->fetchColumn());

            $database = null;
            unlink($copy);
        }
    }

    public function test_relations_are_described_in_gendered_words_from_both_ends(): void
    {
        $athirat = Entity::factory()->for($this->canaanite)->create(['name' => 'Athirat', 'gender' => 'female']);
        $anat = Entity::factory()->for($this->canaanite)->create(['name' => 'Anat', 'gender' => 'female']);
        EntityRelation::create(['entity_id' => $this->el->id, 'related_entity_id' => $athirat->id, 'kind' => RelationKind::Consort]);
        EntityRelation::create(['entity_id' => $this->el->id, 'related_entity_id' => $anat->id, 'kind' => RelationKind::Parent]);

        $relations = collect(json_decode($this->get(route('export', 'json'))->streamedContent(), true)['relations']);

        $consorts = $relations->firstWhere('to', 'athirat');
        $parent = $relations->firstWhere('to', 'anat');

        $this->assertSame(['Husband of', 'Wife of'], [$consorts['from_role'], $consorts['to_role']]);
        $this->assertSame(['Father of', 'Daughter of'], [$parent['from_role'], $parent['to_role']]);

        /** @var BinaryFileResponse $file */
        $file = $this->get(route('export', 'sqlite'))->baseResponse;
        $copy = tempnam(sys_get_temp_dir(), 'export-test-');
        copy($file->getFile()->getPathname(), $copy);
        $database = new PDO('sqlite:'.$copy);

        $this->assertSame(['Father of', 'Daughter of'], $database->query(
            "SELECT rel.from_role, rel.to_role FROM relations rel JOIN entities e ON e.id = rel.related_entity_id WHERE e.slug = 'anat'",
        )->fetch(PDO::FETCH_NUM));

        $database = null;
        unlink($copy);
    }

    public function test_unknown_formats_are_not_found(): void
    {
        $this->get('/export/csv')->assertNotFound();
    }

    public function test_both_the_explorer_and_the_admin_dashboard_offer_the_exports(): void
    {
        $this->get(route('explore'))
            ->assertSee(route('export', 'json'))
            ->assertSee(route('export', 'sqlite'))
            ->assertSee(route('export', 'db'));

        $this->actingAs(User::query()->where('is_admin', true)->sole())
            ->get(route('admin.dashboard'))
            ->assertSee(route('export', 'json'))
            ->assertSee(route('export', 'db'));
    }
}

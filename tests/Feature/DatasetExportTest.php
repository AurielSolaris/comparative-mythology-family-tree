<?php

namespace Tests\Feature;

use App\Enums\RelationCertainty;
use App\Enums\RelationKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use App\Models\Source;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use PDO;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use ZipArchive;

class DatasetExportTest extends TestCase
{
    use RefreshDatabase;

    private Religion $canaanite;

    private Entity $el;

    private Entity $baal;

    protected function setUp(): void
    {
        parent::setUp();

        $this->canaanite = Religion::factory()->create(['name' => 'Canaanite', 'color' => '#aa8800', 'region' => 'Levant', 'time_period' => 'Iron Age']);
        $eblaite = Religion::factory()->create(['name' => 'Eblaite']);

        $this->el = Entity::factory()->for($this->canaanite)->create(['name' => 'El', 'gender' => 'male', 'element' => 'Sky', 'aliases' => ['Ilu', 'ʾĒl']]);
        $this->baal = Entity::factory()->for($this->canaanite)->create(['name' => 'Baal']);
        $this->baal->additionalReligions()->attach($eblaite);

        EntityRelation::create([
            'entity_id' => $this->el->id,
            'related_entity_id' => $this->baal->id,
            'kind' => RelationKind::Council,
            'religion_id' => $this->canaanite->id,
            'certainty' => RelationCertainty::Attested,
            'period' => 'Late Bronze Age',
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

        $this->assertSame(6, $data['format_version']);
        $this->assertSame(['Canaanite', 'Eblaite'], array_column($data['religions'], 'name'));
        $this->assertSame('#aa8800', $data['religions'][0]['color']);
        $this->assertSame(['Levant', 'Iron Age'], [$data['religions'][0]['region'], $data['religions'][0]['time_period']]);
        $this->assertSame([null, null], [$data['religions'][1]['region'], $data['religions'][1]['time_period']]);

        $baal = collect($data['entities'])->firstWhere('name', 'Baal');
        $this->assertSame('canaanite', $baal['religion']);
        $this->assertSame(['eblaite'], $baal['also_religions']);
        $this->assertSame(['Ilu', 'ʾĒl'], collect($data['entities'])->firstWhere('name', 'El')['aliases']);
        $this->assertSame('Sky', collect($data['entities'])->firstWhere('name', 'El')['element']);
        $this->assertNull($baal['element']);
        $this->assertArrayNotHasKey('syncretic_origin', $baal);

        $this->assertSame([[
            'kind' => 'council', 'from' => 'el', 'to' => 'baal',
            'from_role' => 'Council/court includes', 'to_role' => 'Member of the council/court of',
            'tradition' => 'canaanite', 'certainty' => 'attested', 'period' => 'Late Bronze Age',
            'note' => 'divine assembly', 'sides_swapped' => false, 'sources' => [],
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
            $this->assertSame(['Levant', 'Iron Age'], $database->query("SELECT region, time_period FROM religions WHERE name = 'Canaanite'")->fetch(PDO::FETCH_NUM));
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
            $this->assertSame('Sky', $database->query("SELECT element FROM entities WHERE name = 'El'")->fetchColumn());
            $this->assertSame(['attested', 'Late Bronze Age'], $database->query('SELECT certainty, period FROM relations')->fetch(PDO::FETCH_NUM));
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

    public function test_link_sources_are_listed_in_plain_json_and_sqlite_downloads(): void
    {
        Storage::fake(Source::DISK);
        $relation = EntityRelation::query()->sole();
        Source::factory()->for($this->el, 'sourceable')->create(['url' => 'https://example.com/ktu-1.4', 'ref' => 'KTU 1.4 iv 24']);
        Source::factory()->for($relation, 'sourceable')->create(['url' => 'https://example.com/ktu-1.2', 'ref' => null]);

        $response = $this->get(route('export', 'json'))->assertOk();
        $this->assertStringEndsWith('.json', $response->headers->get('Content-Disposition'));
        $data = json_decode($response->streamedContent(), true);

        $this->assertSame([[
            'kind' => 'link', 'url' => 'https://example.com/ktu-1.4', 'file' => null, 'original_name' => null, 'ref' => 'KTU 1.4 iv 24',
        ]], collect($data['entities'])->firstWhere('name', 'El')['sources']);
        $this->assertSame('https://example.com/ktu-1.2', $data['relations'][0]['sources'][0]['url']);

        $database = $this->openSqliteDownload(route('export', 'sqlite'));

        $this->assertSame(['El', 'link', 'https://example.com/ktu-1.4', 'KTU 1.4 iv 24'], $database->query(
            'SELECT e.name, s.kind, s.url, s.ref FROM sources s JOIN entities e ON e.id = s.entity_id',
        )->fetch(PDO::FETCH_NUM));
        $this->assertSame(['council', 'https://example.com/ktu-1.2'], $database->query(
            'SELECT r.kind, s.url FROM sources s JOIN relations r ON r.id = s.relation_id',
        )->fetch(PDO::FETCH_NUM));
        $this->assertSame([], $database->query('PRAGMA foreign_key_check')->fetchAll());
    }

    public function test_file_sources_turn_the_download_into_a_zip_with_the_files(): void
    {
        Storage::fake(Source::DISK);
        $source = Source::factory()->file('baal-cycle.txt')->for($this->baal, 'sourceable')->create(['ref' => 'col. ii, l. 5']);
        Source::factory()->for($this->el, 'sourceable')->create();

        foreach (['json', 'sqlite'] as $format) {
            $response = $this->get(route('export', $format))->assertOk()->assertDownload();
            $this->assertStringEndsWith("-{$format}.zip", $response->headers->get('Content-Disposition'));

            /** @var BinaryFileResponse $file */
            $file = $response->baseResponse;
            $zip = new ZipArchive;
            $zip->open($file->getFile()->getPathname());

            $datasetName = collect(range(0, $zip->numFiles - 1))->map(fn (int $index) => $zip->getNameIndex($index))
                ->first(fn (string $name) => str_ends_with($name, ".{$format}"));
            $this->assertNotNull($datasetName);
            $this->assertSame(Storage::disk(Source::DISK)->get($source->path), $zip->getFromName($source->exportPath()));

            if ($format === 'json') {
                $baal = collect(json_decode($zip->getFromName($datasetName), true)['entities'])->firstWhere('name', 'Baal');
                $this->assertSame([[
                    'kind' => 'file', 'url' => null, 'file' => $source->exportPath(), 'original_name' => 'baal-cycle.txt', 'ref' => 'col. ii, l. 5',
                ]], $baal['sources']);
            }

            $zip->close();
        }
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

    private function openSqliteDownload(string $url): PDO
    {
        /** @var BinaryFileResponse $file */
        $file = $this->get($url)->assertOk()->baseResponse;
        $copy = tempnam(sys_get_temp_dir(), 'export-test-');
        copy($file->getFile()->getPathname(), $copy);
        $this->beforeApplicationDestroyed(fn () => @unlink($copy));

        return new PDO('sqlite:'.$copy);
    }
}

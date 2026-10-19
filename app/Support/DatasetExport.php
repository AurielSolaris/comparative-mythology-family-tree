<?php

namespace App\Support;

use App\Enums\SourceKind;
use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use App\Models\Source;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use PDO;
use RuntimeException;
use ZipArchive;

/**
 * The mythology dataset (religions, entities and relations) as JSON or as a
 * standalone SQLite database. Never includes users or other app internals.
 *
 * Slugs are the portable identifiers: entities and relations refer to
 * religions and entities by slug, and a relation is identified by its kind
 * plus its two entities. The app's own database ids are not exported.
 *
 * Each relation also carries from_role / to_role, the relation read from
 * each end in gendered words ("Father of" / "Daughter of"). They are
 * derived from the entities' genders at export time.
 *
 * Entities and relations list their sources. Link sources carry a URL;
 * file sources carry the file's path inside the export archive
 * ("sources/…"), and when any stored files exist the download is a zip of
 * the JSON / SQLite plus those files (see toArchive()).
 */
class DatasetExport
{
    /**
     * 2: dropped the app's database ids from the JSON; SQLite keys are local to the file.
     * 3: added sources on entities and relations, and the entities' element and syncretic origin.
     * 4: added the co_ruler relation kind.
     * 5: replaced the entities' syncretic origin with syncretism relation kinds
     *    (cognate, analogue, fusion, absorption, avatar, aspect, derivation,
     *    adoption, interpretation, supplanting, fission, demonization,
     *    deification), and added each relation's certainty and period.
     * 6: added the religions' region and time period.
     * 7: added what each entity is in each of its religions (classification
     *    and the tradition's own term), the name_derivation and polemic
     *    relation kinds, and the dataset's licence (CC BY 4.0).
     */
    public const FORMAT_VERSION = 7;

    /**
     * The data is CC BY 4.0 (the code is GPLv3): reusers only need to credit it.
     *
     * @var array{name: string, url: string, attribution: string}
     */
    public const LICENSE = [
        'name' => 'CC BY 4.0',
        'url' => 'https://creativecommons.org/licenses/by/4.0/',
        'attribution' => 'Comparative Mythology Family Tree by Debaditya Malakar, https://github.com/AurielSolaris/comparative-mythology-family-tree',
    ];

    /**
     * @return array{format: string, format_version: int, exported_at: string, license: array{name: string, url: string, attribution: string}, religions: list<array<string, mixed>>, entities: list<array<string, mixed>>, relations: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $religions = Religion::query()->orderBy('name')->get();
        $slugs = $religions->pluck('slug', 'id');

        $entities = Entity::query()->with(['additionalReligions:id', 'sources'])->ordered()->get();
        $entitySlugs = $entities->pluck('slug', 'id');
        $entitiesById = $entities->keyBy('id');

        return [
            'format' => 'comparative-mythology-family-tree',
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'license' => self::LICENSE,
            'religions' => $religions->map(fn (Religion $religion) => [
                'slug' => $religion->slug,
                'name' => $religion->name,
                'color' => $religion->color,
                'region' => $religion->region,
                'time_period' => $religion->time_period,
            ])->all(),
            'entities' => $entities->map(fn (Entity $entity) => [
                'slug' => $entity->slug,
                'name' => $entity->name,
                'gender' => $entity->gender?->value,
                'element' => $entity->element,
                'religion' => $slugs[$entity->religion_id],
                'also_religions' => $entity->additionalReligions->map(fn (Religion $religion) => $slugs[$religion->id])->values()->all(),
                'classification' => $entity->classification?->value,
                'classification_term' => $entity->classification_term,
                'memberships' => $entity->additionalReligions->map(fn (Religion $religion) => [
                    'religion' => $slugs[$religion->id],
                    'classification' => $religion->pivot->classification?->value,
                    'classification_term' => $religion->pivot->classification_term,
                ])->values()->all(),
                'region' => $entity->region,
                'origin' => $entity->origin,
                'aliases' => $entity->aliases ?? [],
                'position' => $entity->position,
                'sources' => $this->sourcesArray($entity->sources),
            ])->all(),
            'relations' => EntityRelation::query()->with('sources')->orderBy('id')->get()->map(fn (EntityRelation $relation) => [
                'kind' => $relation->kind->value,
                'from' => $entitySlugs[$relation->entity_id],
                'to' => $entitySlugs[$relation->related_entity_id],
                'from_role' => $this->roleLabel($relation, $entitiesById[$relation->entity_id]),
                'to_role' => $this->roleLabel($relation, $entitiesById[$relation->related_entity_id]),
                'tradition' => $relation->religion_id ? $slugs[$relation->religion_id] : null,
                'certainty' => $relation->certainty?->value,
                'period' => $relation->period,
                'note' => $relation->note,
                'sides_swapped' => $relation->sides_swapped,
                'sources' => $this->sourcesArray($relation->sources),
            ])->all(),
        ];
    }

    /**
     * @param  Collection<int, Source>  $sources
     * @return list<array{kind: string, url: ?string, file: ?string, original_name: ?string, ref: ?string}>
     */
    protected function sourcesArray(Collection $sources): array
    {
        return $sources->map(fn (Source $source) => [
            'kind' => $source->kind->value,
            'url' => $source->url,
            'file' => $source->exportPath(),
            'original_name' => $source->original_name,
            'ref' => $source->ref,
        ])->values()->all();
    }

    /**
     * File sources whose files are in storage, i.e. what an archive bundles.
     *
     * @return Collection<int, Source>
     */
    public function storedFileSources(): Collection
    {
        return Source::query()
            ->where('kind', SourceKind::File)
            ->whereNotNull('path')
            ->orderBy('id')
            ->get()
            ->filter(fn (Source $source) => $source->hasStoredFile())
            ->values();
    }

    /**
     * Writes a zip holding the dataset (as "$datasetName", in the given
     * format) and every stored source file under sources/, and returns its
     * path. The caller deletes the file when done.
     *
     * @param  'json'|'sqlite'|'db'  $format
     */
    public function toArchive(string $format, string $datasetName): string
    {
        $path = $this->temporaryFile();
        $zip = new ZipArchive;

        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException('Could not create the export archive.');
        }

        $sqlite = null;

        if ($format === 'json') {
            $zip->addFromString($datasetName, $this->toJson());
        } else {
            $sqlite = $this->toSqliteFile();
            $zip->addFile($sqlite, $datasetName);
        }

        $disk = Storage::disk(Source::DISK);

        foreach ($this->storedFileSources() as $source) {
            $zip->addFile($disk->path($source->path), $source->exportPath());
        }

        $zip->close();

        if ($sqlite !== null) {
            unlink($sqlite);
        }

        return $path;
    }

    protected function temporaryFile(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'mythology-export-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the export.');
        }

        return $path;
    }

    /**
     * The relation as read from one of its entities, in that entity's
     * gendered words, e.g. "Wife of".
     */
    protected function roleLabel(EntityRelation $relation, Entity $entity): string
    {
        return $relation->roleFor($entity)->label($entity->gender);
    }

    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    /**
     * Writes the dataset to a new SQLite database file and returns its path.
     * The caller deletes the file when done.
     *
     * Integer keys are assigned fresh inside the file (they only link its
     * tables together); slug columns are the identifiers to rely on.
     */
    public function toSqliteFile(): string
    {
        $path = $this->temporaryFile();
        $data = $this->toArray();
        $database = new PDO('sqlite:'.$path, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

        $database->exec(<<<'SQL'
            PRAGMA foreign_keys = ON;

            CREATE TABLE meta (
                key TEXT PRIMARY KEY,
                value TEXT NOT NULL
            );

            -- The integer ids in this file only link its tables together;
            -- use the slugs to identify religions and entities.
            CREATE TABLE religions (
                id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL UNIQUE,
                color TEXT,
                region TEXT,
                time_period TEXT
            );

            CREATE TABLE entities (
                id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                gender TEXT,
                element TEXT,
                religion_id INTEGER NOT NULL REFERENCES religions (id),
                -- What the entity is in its primary religion: god,
                -- rejected_god, demon or spirit, and that tradition's own word.
                classification TEXT,
                classification_term TEXT,
                region TEXT,
                origin TEXT,
                aliases TEXT NOT NULL, -- JSON array of strings
                position INTEGER NOT NULL
            );

            -- Religions an entity belongs to besides its primary one, and
            -- what it is in each (e.g. Indra: a demon, daēva, in the Avesta).
            CREATE TABLE entity_religions (
                entity_id INTEGER NOT NULL REFERENCES entities (id),
                religion_id INTEGER NOT NULL REFERENCES religions (id),
                classification TEXT,
                classification_term TEXT,
                PRIMARY KEY (entity_id, religion_id)
            );

            -- parent and council: entity_id is the parent / head of the council.
            -- consort, sibling, co_ruler, equivalent, cognate, analogue and polemic are symmetric.
            -- The other syncretism kinds (fusion, absorption, avatar, aspect,
            -- derivation, adoption, interpretation, supplanting, fission,
            -- demonization, deification, name_derivation) point from the
            -- source (entity_id) to the result, e.g. a God to its avatar.
            CREATE TABLE relations (
                id INTEGER PRIMARY KEY,
                kind TEXT NOT NULL,
                entity_id INTEGER NOT NULL REFERENCES entities (id),
                related_entity_id INTEGER NOT NULL REFERENCES entities (id),
                from_role TEXT NOT NULL, -- e.g. 'Father of', read from entity_id (derived from gender)
                to_role TEXT NOT NULL,   -- e.g. 'Daughter of', read from related_entity_id
                tradition_religion_id INTEGER REFERENCES religions (id),
                certainty TEXT, -- 'attested', 'consensus', 'hypothesis' or 'disputed'
                period TEXT,    -- e.g. 'Gupta period'
                note TEXT,
                sides_swapped INTEGER NOT NULL DEFAULT 0,
                UNIQUE (kind, entity_id, related_entity_id)
            );

            -- Citations for an entity or a relation (exactly one is set).
            -- 'link' sources have a url; 'file' sources have the file's path
            -- inside the export zip, e.g. 'sources/abc123.pdf'.
            CREATE TABLE sources (
                id INTEGER PRIMARY KEY,
                entity_id INTEGER REFERENCES entities (id),
                relation_id INTEGER REFERENCES relations (id),
                kind TEXT NOT NULL,
                url TEXT,
                file TEXT,
                original_name TEXT,
                ref TEXT, -- line / verse / page cited
                CHECK ((entity_id IS NULL) <> (relation_id IS NULL))
            );
            SQL);

        $database->beginTransaction();

        $insertMeta = $database->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        $meta = [
            'format' => $data['format'], 'format_version' => $data['format_version'], 'exported_at' => $data['exported_at'],
            'license' => $data['license']['name'], 'license_url' => $data['license']['url'], 'attribution' => $data['license']['attribution'],
        ];
        foreach ($meta as $key => $value) {
            $insertMeta->execute([$key, (string) $value]);
        }

        /** @var array<string, int> $religionIds */
        $religionIds = [];
        $insertReligion = $database->prepare('INSERT INTO religions (slug, name, color, region, time_period) VALUES (?, ?, ?, ?, ?)');
        foreach ($data['religions'] as $religion) {
            $insertReligion->execute([$religion['slug'], $religion['name'], $religion['color'], $religion['region'], $religion['time_period']]);
            $religionIds[$religion['slug']] = (int) $database->lastInsertId();
        }

        /** @var array<string, int> $entityIds */
        $entityIds = [];
        $insertEntity = $database->prepare(
            'INSERT INTO entities (slug, name, gender, element, religion_id, classification, classification_term, region, origin, aliases, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insertMembership = $database->prepare('INSERT INTO entity_religions (entity_id, religion_id, classification, classification_term) VALUES (?, ?, ?, ?)');
        $insertSource = $database->prepare(
            'INSERT INTO sources (entity_id, relation_id, kind, url, file, original_name, ref) VALUES (?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($data['entities'] as $entity) {
            $insertEntity->execute([
                $entity['slug'], $entity['name'], $entity['gender'], $entity['element'], $religionIds[$entity['religion']],
                $entity['classification'], $entity['classification_term'], $entity['region'], $entity['origin'], json_encode($entity['aliases'], JSON_UNESCAPED_UNICODE), $entity['position'],
            ]);
            $entityIds[$entity['slug']] = (int) $database->lastInsertId();

            foreach ($entity['memberships'] as $membership) {
                $insertMembership->execute([
                    $entityIds[$entity['slug']], $religionIds[$membership['religion']], $membership['classification'], $membership['classification_term'],
                ]);
            }

            foreach ($entity['sources'] as $source) {
                $insertSource->execute([$entityIds[$entity['slug']], null, ...array_values($source)]);
            }
        }

        $insertRelation = $database->prepare(
            'INSERT INTO relations (kind, entity_id, related_entity_id, from_role, to_role, tradition_religion_id, certainty, period, note, sides_swapped) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($data['relations'] as $relation) {
            $insertRelation->execute([
                $relation['kind'], $entityIds[$relation['from']], $entityIds[$relation['to']], $relation['from_role'], $relation['to_role'],
                $relation['tradition'] ? $religionIds[$relation['tradition']] : null, $relation['certainty'], $relation['period'],
                $relation['note'], (int) $relation['sides_swapped'],
            ]);
            $relationId = (int) $database->lastInsertId();

            foreach ($relation['sources'] as $source) {
                $insertSource->execute([null, $relationId, ...array_values($source)]);
            }
        }

        $database->commit();
        $database = null;

        return $path;
    }
}

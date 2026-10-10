<?php

namespace App\Support;

use App\Models\Entity;
use App\Models\EntityRelation;
use App\Models\Religion;
use PDO;
use RuntimeException;

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
 */
class DatasetExport
{
    /**
     * 2: dropped the app's database ids from the JSON; SQLite keys are local to the file.
     */
    public const FORMAT_VERSION = 2;

    /**
     * @return array{format: string, format_version: int, exported_at: string, religions: list<array<string, mixed>>, entities: list<array<string, mixed>>, relations: list<array<string, mixed>>}
     */
    public function toArray(): array
    {
        $religions = Religion::query()->orderBy('name')->get();
        $slugs = $religions->pluck('slug', 'id');

        $entities = Entity::query()->with('additionalReligions:id')->ordered()->get();
        $entitySlugs = $entities->pluck('slug', 'id');
        $entitiesById = $entities->keyBy('id');

        return [
            'format' => 'comparative-mythology-family-tree',
            'format_version' => self::FORMAT_VERSION,
            'exported_at' => now()->toIso8601String(),
            'religions' => $religions->map(fn (Religion $religion) => [
                'slug' => $religion->slug,
                'name' => $religion->name,
                'color' => $religion->color,
            ])->all(),
            'entities' => $entities->map(fn (Entity $entity) => [
                'slug' => $entity->slug,
                'name' => $entity->name,
                'gender' => $entity->gender?->value,
                'religion' => $slugs[$entity->religion_id],
                'also_religions' => $entity->additionalReligions->map(fn (Religion $religion) => $slugs[$religion->id])->values()->all(),
                'region' => $entity->region,
                'origin' => $entity->origin,
                'aliases' => $entity->aliases ?? [],
                'position' => $entity->position,
            ])->all(),
            'relations' => EntityRelation::query()->orderBy('id')->get()->map(fn (EntityRelation $relation) => [
                'kind' => $relation->kind->value,
                'from' => $entitySlugs[$relation->entity_id],
                'to' => $entitySlugs[$relation->related_entity_id],
                'from_role' => $this->roleLabel($relation, $entitiesById[$relation->entity_id]),
                'to_role' => $this->roleLabel($relation, $entitiesById[$relation->related_entity_id]),
                'tradition' => $relation->religion_id ? $slugs[$relation->religion_id] : null,
                'note' => $relation->note,
                'sides_swapped' => $relation->sides_swapped,
            ])->all(),
        ];
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
        $path = tempnam(sys_get_temp_dir(), 'mythology-export-');

        if ($path === false) {
            throw new RuntimeException('Could not create a temporary file for the export.');
        }

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
                color TEXT
            );

            CREATE TABLE entities (
                id INTEGER PRIMARY KEY,
                slug TEXT NOT NULL UNIQUE,
                name TEXT NOT NULL,
                gender TEXT,
                religion_id INTEGER NOT NULL REFERENCES religions (id),
                region TEXT,
                origin TEXT,
                aliases TEXT NOT NULL, -- JSON array of strings
                position INTEGER NOT NULL
            );

            -- Religions an entity belongs to besides its primary one.
            CREATE TABLE entity_religions (
                entity_id INTEGER NOT NULL REFERENCES entities (id),
                religion_id INTEGER NOT NULL REFERENCES religions (id),
                PRIMARY KEY (entity_id, religion_id)
            );

            -- parent and council: entity_id is the parent / head of the council.
            -- consort, sibling and equivalent are symmetric.
            CREATE TABLE relations (
                id INTEGER PRIMARY KEY,
                kind TEXT NOT NULL,
                entity_id INTEGER NOT NULL REFERENCES entities (id),
                related_entity_id INTEGER NOT NULL REFERENCES entities (id),
                from_role TEXT NOT NULL, -- e.g. 'Father of', read from entity_id (derived from gender)
                to_role TEXT NOT NULL,   -- e.g. 'Daughter of', read from related_entity_id
                tradition_religion_id INTEGER REFERENCES religions (id),
                note TEXT,
                sides_swapped INTEGER NOT NULL DEFAULT 0,
                UNIQUE (kind, entity_id, related_entity_id)
            );
            SQL);

        $database->beginTransaction();

        $insertMeta = $database->prepare('INSERT INTO meta (key, value) VALUES (?, ?)');
        foreach (['format' => $data['format'], 'format_version' => $data['format_version'], 'exported_at' => $data['exported_at']] as $key => $value) {
            $insertMeta->execute([$key, (string) $value]);
        }

        /** @var array<string, int> $religionIds */
        $religionIds = [];
        $insertReligion = $database->prepare('INSERT INTO religions (slug, name, color) VALUES (?, ?, ?)');
        foreach ($data['religions'] as $religion) {
            $insertReligion->execute([$religion['slug'], $religion['name'], $religion['color']]);
            $religionIds[$religion['slug']] = (int) $database->lastInsertId();
        }

        /** @var array<string, int> $entityIds */
        $entityIds = [];
        $insertEntity = $database->prepare(
            'INSERT INTO entities (slug, name, gender, religion_id, region, origin, aliases, position) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        $insertMembership = $database->prepare('INSERT INTO entity_religions (entity_id, religion_id) VALUES (?, ?)');
        foreach ($data['entities'] as $entity) {
            $insertEntity->execute([
                $entity['slug'], $entity['name'], $entity['gender'], $religionIds[$entity['religion']],
                $entity['region'], $entity['origin'], json_encode($entity['aliases'], JSON_UNESCAPED_UNICODE), $entity['position'],
            ]);
            $entityIds[$entity['slug']] = (int) $database->lastInsertId();

            foreach ($entity['also_religions'] as $slug) {
                $insertMembership->execute([$entityIds[$entity['slug']], $religionIds[$slug]]);
            }
        }

        $insertRelation = $database->prepare(
            'INSERT INTO relations (kind, entity_id, related_entity_id, from_role, to_role, tradition_religion_id, note, sides_swapped) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
        );
        foreach ($data['relations'] as $relation) {
            $insertRelation->execute([
                $relation['kind'], $entityIds[$relation['from']], $entityIds[$relation['to']], $relation['from_role'], $relation['to_role'],
                $relation['tradition'] ? $religionIds[$relation['tradition']] : null, $relation['note'], (int) $relation['sides_swapped'],
            ]);
        }

        $database->commit();
        $database = null;

        return $path;
    }
}

# Comparative Mythology Family Tree

A web app for exploring the family trees of gods, heroes and other mythological figures, and for comparing them across religions and pantheons.

Visitors explore an interactive canvas: they can pan and zoom, search, open any entity's family, or put several religions side by side. Administrators add and edit the data through a separate dashboard.

## Screenshots

| Landing page | Explorer |
| --- | --- |
| ![The landing page](public/images/screenshots/landing.png) | ![The canvas explorer centred on Baal](public/images/screenshots/explorer.png) |

| Comparing religions side by side |
| --- |
| ![Canaan and Eblaite compared in columns](public/images/screenshots/compare.png) |

## Features

### Landing page (`/`)

A short introduction to the project for visitors, with links into the explorer and the exports. Logged-in admins are sent straight to `/admin`.

### Public explorer (`/explore`)

- **Canvas family tree.** Generations are laid out as rows, with parents above children and partners side by side. Drag to pan, scroll or pinch to zoom, click an entity for its details, and double-click to re-centre the tree on it.
- **Relations.**
  - Parent → child lines are labelled *son*, *daughter* or *child*.
  - Consorts are joined by a double line, siblings by a dashed arc.
  - Equivalents across religions (Zeus ≈ Jupiter) are joined by a dotted arc.
  - Council/court membership is an arrow from the head to the member.

  Each line carries its tradition and note when they are recorded (e.g. *son · Eblaite*). Labels are only drawn where there is room.
- **Search** by name, alias (including non-Latin scripts), region or origin, optionally filtered by gender.
- **Browse a religion** to see all of its members. Entities that belong to several pantheons appear in each of them.
- **Compare side by side.** Pick 2–4 religions and each one gets its own column. Generations line up across columns, so a parent in one pantheon still sits above a child in another. The link can be shared, e.g. `/compare?religions=eblaite,canaanite`.
- **Depth control.** On an entity's page, choose how many relation hops around it are shown (1–4).

### Export

Anyone can download the whole dataset: visitors from the explorer's left panel, admins from the dashboard's **Export** button.

| Format | URL | Contents |
| --- | --- | --- |
| JSON | `/export/json` | `religions`, `entities` and `relations` |
| SQLite | `/export/sqlite` or `/export/db` | The same data as a standalone database with foreign keys. It has the tables `religions`, `entities`, `entity_religions`, `relations` and `meta`. Both URLs return the same file; only the extension differs. |

- **Identifiers.** Religions and entities are identified by their slug, and relations refer to entities by slug. A relation is identified by its `kind`, `from` and `to`. The app's own database ids are not exported. The integer keys in the SQLite file only link its tables together and are numbered fresh for each export.
- **Role words.** Each relation includes `from_role` and `to_role`: the relation read from each end, in that entity's gendered words (e.g. `"Father of"` / `"Daughter of"`, `"Husband of"` / `"Wife of"`). They are computed from the entities' genders at export time. If you change a gender in an exported file, change these too; `gender` is the source of truth.
- **Versioning.** The file's `format_version` is currently 2.
- **Privacy.** Exports never include user accounts or other app internals.

### Admin dashboard (`/admin`)

- **Religions** with an optional colour, used for node stripes, comparison columns and labels.
- **Entities**, each with:
  - name, gender, region/area, origin and aliases (one per line);
  - a primary religion, plus any other religions it also belongs to (e.g. *Baal*: Canaanite, also Eblaite).
- **Relations:** *parent/child*, *consort*, *sibling*, *equivalent* and *member of council/court*. Each relation can have an optional tradition and a note. Duplicates, self-links and contradictory links are rejected.
- **Drag-to-reorder** the entity list. This order is also the starting left-to-right order of siblings on the canvas.
- **Switch sides (⇄).** While logged in as an admin, each consort or sibling pair on the canvas has a ⇄ button that swaps which one is drawn on the left.

There is no public registration. Only admins can log in, and only they can change data.

## Tech stack

| Area | Choice |
| --- | --- |
| Backend | Laravel 13, PHP 8.5 |
| Database | SQLite |
| Cache | [CitronDB](https://github.com/AurielSolaris/CitronDB), via a custom PHP driver (see below) |
| Views | Blade templates, styled with [UIkit](https://getuikit.com) |
| Frontend scripts | TypeScript, Alpine.js, axios |
| Tree rendering | HTML `<canvas>`, custom layout and renderer (`resources/js/explorer`) |
| Package manager / bundler | Bun, Vite |
| Tests | PHPUnit |

## Getting started

### Requirements

- PHP 8.5 and Composer. The quickest way to install both on Windows, macOS or Linux is [php.new](https://php.new).
- [Bun](https://bun.sh).

### Install

```bash
composer setup
```

This installs the PHP and frontend dependencies, creates `.env`, generates the app key, runs the migrations and builds the frontend assets. If it reports that the SQLite database is missing, create an empty `database/database.sqlite` and run it again.

### Create an administrator

```bash
php artisan app:create-admin
```

The command asks for an email address, a name and a password (at least 8 characters). If the email already belongs to a user, it offers to make that user an admin instead.

### Run

```bash
composer run dev
```

| URL | What it is |
| --- | --- |
| http://localhost:8000 | The app: the explorer, plus `/login` and `/admin` |
| http://localhost:5173 | Vite's asset server. It only serves scripts and styles; open the app on port 8000. |

A good order for entering data: religions first, then entities, then relations (from each entity's edit page).

## Project layout

```
app/
  Cache/            CitronStore and CitronLock: the Laravel cache driver
  Citron/           CitronDB: a pure-PHP implementation of the CitronDB file format
  Enums/            Gender, RelationKind, RelationRole
  Http/Controllers/ Explorer and graph JSON (public), Admin/* (dashboard)
  Models/           Religion, Entity, EntityRelation
  Support/          EntityGraph: builds the node/edge JSON the canvas draws
resources/
  js/explorer/      Canvas tree: layout.ts, renderer.ts, explorer.ts (Alpine)
  js/admin/         Entity picker, sortable rows, confirmation dialogs
  views/            explorer.blade.php, admin/*, auth/login
docker/             Apache, PHP and entrypoint config for the container image
```

### Data model

- **Religion:** name, slug and optional colour.
- **Entity:**
  - name, slug, gender, region, origin, aliases and manual position;
  - a primary religion (`religion_id`) plus additional religions (the `entity_religion` table).
- **EntityRelation:** two entities, a kind, an optional tradition and an optional note.
  - *parent* and *council* are directional: `entity_id` is the parent, or the head of the council.
  - *consort*, *sibling* and *equivalent* are symmetric and stored once, with the lower id first. `sides_swapped` records the admin's chosen left/right order.

### Graph endpoints (used by the canvas)

| Endpoint | Returns |
| --- | --- |
| `GET /graph/entities/{slug}?depth=1-4` | An entity and its neighbourhood |
| `GET /graph/religions/{slug}` | A religion's members, plus their equivalents in other religions |
| `GET /graph/compare?religions=a,b` | 2–4 religions as side-by-side columns |
| `GET /search/suggestions?q=…&gender=…` | Search suggestions |
| `GET /export/{json,sqlite,db}` | Download the whole dataset |

## The CitronDB cache driver

CitronDB is a single-file key-value database written in Perl, with official bindings for C and Python only. This app talks to it through a pure-PHP implementation of the file format in `app/Citron/CitronDB.php`, so it needs neither Perl, a compiled library nor PHP's FFI extension.

- **Compatible with the official tools.** It uses the same file format (version 2), the same `<db>.lock` file locking, and the same atomic writes (write to a temporary file, flush it to disk, rename it over the database). The Perl, C and Python tools can therefore use the same file at the same time.
- **Laravel cache store.** The `citron` store (`app/Cache/CitronStore.php`) supports expiry, tags, atomic `add`/`increment`, and locks. The cache file is `storage/framework/cache/cache.citron`.
- **Windows notes.**
  - The interop with Perl was checked against native Strawberry Perl. Git Bash's bundled MSYS Perl locks files in a way that no native implementation (C, Python or this driver) can see, so don't use it on a shared database.
  - Replacing the file is briefly retried, in case a virus scanner is holding it open.

## Tests

```bash
php artisan test        # PHP feature and unit tests
bun run typecheck       # TypeScript
vendor/bin/pint         # code style
```

## Docker

```bash
php artisan key:generate --show          # copy the key it prints
APP_KEY=base64:... docker compose up --build
docker compose exec app php artisan app:create-admin
```

The app is then available at http://localhost:8080.

- **Image.** A multi-stage build: Bun builds the assets, and the app runs on PHP 8.5 with Apache.
- **Persistent data.** The SQLite database and the CitronDB cache live on named volumes.
- **Startup.** Migrations run automatically when the container starts.
- **HTTPS.** If the container runs behind a reverse proxy that handles HTTPS, configure Laravel's trusted proxies.

## License

Copyright © 2026 Debaditya Malakar. Released under the [GNU General Public License v3.0](LICENSE).

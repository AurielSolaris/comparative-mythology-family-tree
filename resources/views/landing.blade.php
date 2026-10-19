@php
    $repositoryUrl = config('app.repository_url');

    $compareUrl = $religions->count() >= 2
        ? route('compare', ['religions' => $religions->take(2)->pluck('slug')->join(',')])
        : route('explore');

    $features = [
        ['sprite' => 'ziggurat', 'title' => 'Interactive canvas', 'text' => 'Pan, zoom and search across pantheons. Double-click a God to put them at the centre of the universe. They tend to approve.'],
        ['sprite' => 'trident', 'title' => 'Cross-pantheon relations', 'text' => 'Family lines, consorts and councils, plus every kind of syncretism: equivalents, cognates, fusions, avatars, adoptions and more, each marked attested, agreed or disputed. Some Gods belong to two pantheons, so the tree does too.'],
        ['sprite' => 'tablet', 'title' => 'Open data', 'text' => 'Export everything as JSON or SQLite. Versioned, slug-based, and yours to take elsewhere.'],
    ];

    $rows = [
        [
            'label' => 'Explore',
            'title' => 'One canvas, every generation.',
            'text' => 'Parents above, children below, consorts side by side. Search by name, alias or region, then pick a God and set how many relations out you want to see.',
            'screenshot' => 'images/screenshots/explorer.png',
            'alt' => 'The canvas explorer showing a family tree',
        ],
        [
            'label' => 'Compare',
            'title' => 'Pantheons, side by side.',
            'text' => 'Pick two to four religions and each one gets a column. Generations line up across them, and syncretic links (equivalents, adoptions, fusions…) arc between the columns. The link is shareable.',
            'screenshot' => 'images/screenshots/compare.png',
            'alt' => 'Two religions compared in side-by-side columns',
        ],
        [
            'label' => 'Curate',
            'title' => 'A plain admin, on purpose.',
            'text' => 'Data goes in through forms and tables. Relations carry their tradition and a note, so “son of Dagan, per the Eblaite texts” survives the trip.',
            'screenshot' => 'images/screenshots/admin.png',
            'alt' => 'The admin dashboard',
        ],
    ];

    $stack = ['Laravel', 'PHP', 'SQLite', 'CitronDB', 'Alpine.js', 'TypeScript'];
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="description" content="An interactive family tree of Gods, cross-referenced across pantheons, with open data exports.">

        <title>{{ config('app.name') }}</title>
        <x-favicons />
        <meta property="og:title" content="{{ config('app.name') }}">
        <meta property="og:description" content="An interactive family tree of Gods, cross-referenced across pantheons, with open data exports.">
        <meta property="og:image" content="{{ asset('icon-512.png') }}">

        @fonts(['lora', 'inter', 'press-start-2p'])

        @vite(['resources/css/landing.css', 'resources/js/landing.ts'])
    </head>
    <body class="landing">
        <header class="landing-header" uk-sticky>
            <div class="uk-container landing-container">
                <nav class="uk-navbar-container uk-navbar-transparent" uk-navbar>
                    <div class="uk-navbar-left">
                        <a class="uk-navbar-item uk-logo landing-logo" href="{{ route('home') }}" aria-label="{{ config('app.name') }}"><img class="app-logo" src="{{ asset('favicon.svg') }}" width="32" height="32" alt=""> CMFT</a>
                        <ul class="uk-navbar-nav uk-visible@m">
                            <li><a href="{{ route('explore') }}">Explore</a></li>
                            <li><a href="#religions">Religions</a></li>
                            <li><a href="{{ $compareUrl }}">Compare</a></li>
                            <li><a href="#data">Export</a></li>
                        </ul>
                    </div>
                    <div class="uk-navbar-right">
                        <a class="uk-navbar-item landing-icon-link" href="{{ $repositoryUrl }}" uk-icon="github" aria-label="Source code on GitHub"></a>
                        <div class="uk-navbar-item">
                            <a class="uk-button uk-button-primary uk-button-small" href="{{ route('explore') }}">Explore →</a>
                        </div>
                    </div>
                </nav>
            </div>
        </header>

        <main>
            <section class="landing-hero">
                <div class="uk-container landing-container">
                    <div class="uk-grid-large uk-flex-middle" uk-grid>
                        <div class="uk-width-3-5@m">
                            <p class="landing-label">Ancient Near East</p>
                            <h1 class="landing-hero-title">Religious history,<br>one click away.</h1>
                            <p class="landing-lead">Gods. Family trees. Cross-referenced. An interactive map of who begat whom, who married whom, and who turns out to be the same God under another name.</p>
                            <div class="landing-actions">
                                <a class="uk-button uk-button-primary" href="{{ route('explore') }}">Explore the tree →</a>
                                <a class="uk-button uk-button-default" href="{{ $repositoryUrl }}">View on GitHub</a>
                            </div>
                        </div>
                        <div class="uk-width-2-5@m uk-visible@s">
                            <div class="landing-hero-sprites" aria-hidden="true">
                                <canvas class="landing-sprite landing-sprite-bull landing-bob" data-sprite="bull" data-pixel="7"></canvas>
                                <canvas class="landing-sprite landing-sprite-moon" data-sprite="moon" data-pixel="5"></canvas>
                                <canvas class="landing-sprite landing-sprite-bolt landing-bob landing-bob-late" data-sprite="bolt" data-pixel="5"></canvas>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <section class="landing-section">
                <div class="uk-container landing-container">
                    <h2 class="landing-heading">What it is</h2>
                    <div class="uk-child-width-1-3@m uk-grid-match uk-margin-medium-top" uk-grid>
                        @foreach ($features as $feature)
                            <div>
                                <div class="uk-card uk-card-body landing-card">
                                    <div class="landing-card-sprite"><canvas class="landing-sprite" data-sprite="{{ $feature['sprite'] }}" data-pixel="4" aria-hidden="true"></canvas></div>
                                    <h3 class="landing-card-title">{{ $feature['title'] }}</h3>
                                    <p>{{ $feature['text'] }}</p>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            </section>

            <section class="landing-section">
                <div class="uk-container landing-container">
                    <h2 class="landing-heading">How it works</h2>
                    @foreach ($rows as $row)
                        <div @class(['uk-grid-large uk-flex-middle landing-row', 'uk-flex-row-reverse' => $loop->even]) uk-grid>
                            <div class="uk-width-2-5@m">
                                <p class="landing-label">{{ $row['label'] }}</p>
                                <h3 class="landing-subheading">{{ $row['title'] }}</h3>
                                <p>{{ $row['text'] }}</p>
                            </div>
                            <div class="uk-width-3-5@m">
                                @if (file_exists(public_path($row['screenshot'])))
                                    <img class="landing-screenshot" src="{{ asset($row['screenshot']) }}" alt="{{ $row['alt'] }}" loading="lazy">
                                @else
                                    <div class="landing-screenshot landing-screenshot-placeholder" role="img" aria-label="{{ $row['alt'] }}">Screenshot</div>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </section>

            <section class="landing-section landing-band" id="data">
                <div class="uk-container uk-container-small">
                    <h2 class="landing-heading">Built on open data.</h2>
                    <p>The whole dataset downloads in one go. Religions and Gods are identified by slug, relations by their kind and two ends, and the file says which format version it speaks. Currently 2. Nothing in it depends on this app.</p>
                    <pre class="landing-code"><code>{
  "format": "comparative-mythology-family-tree",
  "format_version": 2,
  "entities": [
    { "slug": "baal", "name": "Baal", "gender": "male",
      "religion": "canaan", "also_religions": ["eblaite"] }
  ],
  "relations": [
    { "kind": "parent", "from": "dagan", "to": "baal",
      "from_role": "Father of", "to_role": "Son of" }
  ]
}</code></pre>
                    <div class="landing-actions">
                        <a class="uk-button uk-button-primary" href="{{ route('export', 'json') }}">Download JSON</a>
                        <a class="uk-button uk-button-default" href="{{ route('export', 'sqlite') }}">Download SQLite</a>
                    </div>
                </div>
            </section>

            <section class="landing-section" id="religions">
                <div class="uk-container landing-container">
                    <h2 class="landing-heading">Pantheons so far</h2>
                    <div class="landing-chips">
                        @foreach ($religions as $religion)
                            <a class="landing-chip" href="{{ route('religions.show', $religion) }}">
                                {{ $religion->name }} <span class="landing-chip-count">{{ $religion->memberCount() }}</span>
                            </a>
                        @endforeach
                        <span class="landing-chip landing-chip-muted">more to come</span>
                    </div>
                </div>
            </section>

            <section class="landing-section landing-stack">
                <div class="uk-container landing-container">
                    <ul class="landing-badges">
                        @foreach ($stack as $technology)
                            <li class="landing-badge">{{ $technology }}</li>
                        @endforeach
                    </ul>
                    <p class="landing-caption">Built with boring technology and one homemade database.</p>
                </div>
            </section>
        </main>

        <footer class="landing-footer">
            <div class="uk-container landing-container">
                <div class="uk-flex uk-flex-between uk-flex-middle uk-flex-wrap">
                    <p>Made by Debaditya Malakar (AurielSolaris)</p>
                    <ul class="landing-footer-links">
                        <li><a href="{{ $repositoryUrl }}"><span uk-icon="github"></span> GitHub</a></li>
                        <li><a href="{{ $repositoryUrl }}/blob/main/LICENSE">Code: GPLv3</a></li>
                        <li><a href="{{ $repositoryUrl }}/blob/main/LICENSE-DATA">Data: CC BY 4.0</a></li>
                    </ul>
                </div>
            </div>
        </footer>
    </body>
</html>

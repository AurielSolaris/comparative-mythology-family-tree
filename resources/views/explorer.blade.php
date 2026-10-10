@extends('layouts.base')

@if ($title)
    @section('title', $title)
@endif

@php
    $config = [
        'appName' => config('app.name'),
        'graphUrl' => $graphUrl,
        'suggestUrl' => route('search.suggestions'),
        'religionPageUrl' => route('religions.show', '__SLUG__'),
        'religionGraphUrl' => route('graph.religions', '__SLUG__'),
        'adminEditUrl' => auth()->user()?->can('access-admin') ? route('admin.entities.edit', '__ID__') : null,
        'swapSidesUrl' => auth()->user()?->can('access-admin') ? route('admin.relations.sides', '__ID__') : null,
        'religions' => $religions->map->only('slug', 'name')->values(),
        'religionsPageUrl' => route('compare'),
        'compareGraphUrl' => route('graph.compare'),
        'selectedReligions' => $selectedReligions,
    ];
@endphp

@section('body')
    <div class="explorer" x-data="explorer(@js($config))">
        <canvas class="explorer-canvas" x-ref="canvas" tabindex="0"
                aria-label="Family tree. Drag to move, scroll or pinch to zoom, click an entity for details, double-click to centre the tree on it."></canvas>

        {{-- Left column: search and browse on top, legend at the bottom. They share one
             column so a short window shrinks the search card instead of overlapping. --}}
        <div class="explorer-sidebar uk-position-z-index">
            <div class="uk-card uk-card-default uk-card-small uk-card-body explorer-sidebar-search">
                <a class="uk-logo uk-text-primary" href="{{ route('home') }}">{{ config('app.name') }}</a>

                <div class="uk-margin-small-top uk-inline uk-width-1-1" @click.outside="suggestionsOpen = false">
                    <span class="uk-form-icon" uk-icon="search"></span>
                    <input class="uk-input" type="search" autocomplete="off" aria-label="Search entities"
                           placeholder="Search names, aliases, regions…"
                           x-model="query"
                           @input.debounce.250ms="suggest"
                           @focus="suggestionsOpen = suggestions.length > 0 && query !== ''"
                           @keydown.arrow-down.prevent="moveHighlight(1)"
                           @keydown.arrow-up.prevent="moveHighlight(-1)"
                           @keydown.enter.prevent="openHighlighted"
                           @keydown.escape="suggestionsOpen = false">

                    <div class="uk-drop uk-dropdown uk-width-1-1 uk-margin-small-top" :class="{ 'uk-open': suggestionsOpen }">
                        <ul class="uk-nav uk-dropdown-nav">
                            <template x-for="(suggestion, index) in suggestions" :key="suggestion.id">
                                <li :class="{ 'uk-active': index === highlighted }">
                                    <a :href="suggestion.url" @click.prevent="openSuggestion(suggestion)" @mouseenter="highlighted = index">
                                        <span x-text="suggestion.name"></span>
                                        <div class="uk-nav-subtitle" x-text="[suggestion.religion, suggestion.gender, suggestion.region].filter(Boolean).join(' · ')"></div>
                                    </a>
                                </li>
                            </template>
                            <li class="uk-nav-header" x-show="suggestions.length === 0">No entities found</li>
                        </ul>
                    </div>
                </div>

                <div class="uk-grid-small uk-margin-small-top" uk-grid>
                    <div class="uk-width-expand">
                        <select class="uk-select uk-form-small" aria-label="Only search this gender" x-model="gender" @change="suggest">
                            <option value="">Any gender</option>
                            @foreach ($genders as $gender)
                                <option value="{{ $gender->value }}">{{ $gender->symbol() }} {{ $gender->label() }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="uk-width-auto" x-show="isEntityGraph" x-cloak>
                        <select class="uk-select uk-form-small" aria-label="Generations shown" x-model="depth" @change="reload" uk-tooltip="Relation hops shown around the centre">
                            <option value="1">1 hop</option>
                            <option value="2">2 hops</option>
                            <option value="3">3 hops</option>
                            <option value="4">4 hops</option>
                        </select>
                    </div>
                </div>

                <hr class="uk-margin-small">

                <div class="explorer-sidebar-scroll">

                <div class="uk-grid-small" uk-grid>
                    <div class="uk-width-1-1">
                        <select class="uk-select uk-form-small" aria-label="Browse a religion" x-model="religion" @change="openReligion">
                            <option value="">Browse a religion…</option>
                            @foreach ($religions as $religion)
                                <option value="{{ $religion->slug }}">{{ $religion->name }} ({{ $religion->memberCount() }})</option>
                            @endforeach
                        </select>
                    </div>
                    @if ($religions->count() >= 2)
                        <div class="uk-width-1-1">
                            <button class="uk-button uk-button-default uk-button-small uk-width-1-1" type="button" @click="compareOpen = !compareOpen" :aria-expanded="compareOpen">
                                <span uk-icon="icon: thumbnails; ratio: 0.8"></span> Compare side by side
                            </button>
                            <div class="uk-margin-small-top" x-show="compareOpen" x-cloak>
                                <div class="uk-text-meta">Tick 2–4 religions, in the order they should appear left to right.</div>
                                <ul class="uk-list uk-list-collapse uk-margin-small explorer-compare-list">
                                    @foreach ($religions as $religion)
                                        <li>
                                            <label>
                                                <input class="uk-checkbox" type="checkbox" :checked="compared.includes(@js($religion->slug))" @change="toggleCompared(@js($religion->slug))">
                                                {{ $religion->name }}
                                                <span class="uk-badge" x-show="compared.includes(@js($religion->slug))" x-text="comparedPosition(@js($religion->slug))"></span>
                                            </label>
                                        </li>
                                    @endforeach
                                </ul>
                                <button class="uk-button uk-button-primary uk-button-small uk-width-1-1" type="button" :disabled="!canCompare" @click="openComparison">
                                    Show side by side
                                </button>
                            </div>
                        </div>
                    @endif
                </div>

                <div class="uk-margin-small-top">
                    <div class="uk-text-meta"><span uk-icon="icon: download; ratio: 0.8"></span> Export all data</div>
                    <ul class="uk-subnav uk-subnav-divider uk-margin-remove-top uk-margin-small-bottom">
                        <li><a href="{{ route('export', 'json') }}" download>JSON</a></li>
                        <li><a class="uk-text-lowercase" href="{{ route('export', 'sqlite') }}" download uk-tooltip="SQLite database">.sqlite</a></li>
                        <li><a class="uk-text-lowercase" href="{{ route('export', 'db') }}" download uk-tooltip="SQLite database">.db</a></li>
                    </ul>
                </div>
                </div>
            </div>

            {{-- Legend and view controls --}}
            <div class="uk-card uk-card-default uk-card-small uk-card-body explorer-sidebar-legend" x-data="{ legendOpen: window.innerHeight >= 760 }">
                <div class="uk-grid-small uk-flex-middle" uk-grid>
                    <div class="uk-width-expand">
                        <button class="uk-button uk-button-text" type="button" @click="legendOpen = !legendOpen" :aria-expanded="legendOpen">
                            Legend
                            <span x-show="legendOpen" uk-icon="icon: chevron-down; ratio: 0.8"></span>
                            <span x-show="!legendOpen" uk-icon="icon: chevron-up; ratio: 0.8"></span>
                        </button>
                    </div>
                    <div class="uk-width-auto">
                        <button class="uk-icon-button" type="button" uk-icon="plus" @click="zoomIn" uk-tooltip="Zoom in (+)" aria-label="Zoom in"></button>
                        <button class="uk-icon-button" type="button" uk-icon="minus" @click="zoomOut" uk-tooltip="Zoom out (−)" aria-label="Zoom out"></button>
                        <button class="uk-icon-button" type="button" uk-icon="expand" @click="fit" uk-tooltip="Fit to screen (0)" aria-label="Fit to screen"></button>
                    </div>
                </div>
                <ul class="uk-list uk-list-collapse uk-text-small uk-margin-small-top" x-show="legendOpen" x-cloak>
                    <li><svg class="explorer-legend" viewBox="0 0 28 10" aria-hidden="true"><path d="M2 1v4h24v4" fill="none" stroke="#b4b4b4" stroke-width="1.5"/></svg> Parent → child</li>
                    <li><svg class="explorer-legend" viewBox="0 0 28 10" aria-hidden="true"><path d="M2 3h24M2 7h24" stroke="#f0506e" stroke-width="1.5"/></svg> Consorts</li>
                    <li><svg class="explorer-legend" viewBox="0 0 28 10" aria-hidden="true"><path d="M2 5h24" stroke="#999999" stroke-width="1.5" stroke-dasharray="5 4"/></svg> Siblings</li>
                    <li><svg class="explorer-legend" viewBox="0 0 28 10" aria-hidden="true"><path d="M2 5h24" stroke="#1e87f0" stroke-width="2" stroke-dasharray="1 4" stroke-linecap="round"/></svg> Equivalent in another religion</li>
                    <li><svg class="explorer-legend" viewBox="0 0 28 10" aria-hidden="true"><path d="M2 5h17" stroke="#faa05a" stroke-width="1.5" stroke-dasharray="6 3 2 3"/><path d="M19 1.5 26 5l-7 3.5z" fill="#faa05a"/></svg> Council / court (head → member)</li>
                </ul>
                <p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom" x-show="graph?.truncated" x-cloak>Only part of this tree is shown.</p>
                @can('access-admin')
                    <p class="uk-text-meta uk-margin-small-top uk-margin-remove-bottom uk-visible@s">
                        <span class="uk-text-primary">⇄</span> on a pair switches its sides
                    </p>
                @endcan
            </div>
        </div>

        {{-- Details of the selected entity --}}
        <template x-if="selected">
            <div class="uk-position-top-right uk-position-small uk-position-z-index explorer-panel">
                <div class="uk-card uk-card-default uk-card-small">
                    <div class="uk-card-header">
                        <div class="uk-grid-small" uk-grid>
                            <div class="uk-width-expand">
                                <h2 class="uk-card-title uk-margin-remove" x-text="selected.name"></h2>
                                <span class="uk-label explorer-religion-label" :style="{ backgroundColor: religionColor(selected.religion) }" x-text="selected.religion.name"></span>
                                <template x-for="religion in selected.otherReligions" :key="religion.slug">
                                    <span class="uk-label explorer-religion-label" :style="{ backgroundColor: religionColor(religion) }" x-text="'also ' + religion.name"></span>
                                </template>
                            </div>
                            <div class="uk-width-auto">
                                <button class="uk-close-large" type="button" uk-close aria-label="Close details" @click="closeDetails"></button>
                            </div>
                        </div>
                    </div>

                    <div class="uk-card-body explorer-details">
                        <dl class="uk-description-list">
                            <template x-if="selected.gender">
                                <div>
                                    <dt>Gender</dt>
                                    <dd x-text="selected.gender.symbol + ' ' + selected.gender.label"></dd>
                                </div>
                            </template>
                            <template x-if="selected.region">
                                <div>
                                    <dt>Region</dt>
                                    <dd x-text="selected.region"></dd>
                                </div>
                            </template>
                            <template x-if="selected.origin">
                                <div>
                                    <dt>Origin</dt>
                                    <dd x-text="selected.origin"></dd>
                                </div>
                            </template>
                            <template x-if="selected.aliases.length">
                                <div>
                                    <dt>Also known as</dt>
                                    <dd x-text="selected.aliases.join(', ')"></dd>
                                </div>
                            </template>
                        </dl>

                        <h3 class="uk-heading-bullet uk-h5">Relations</h3>
                        <p class="uk-text-meta" x-show="relatives.length === 0">None shown in this view.</p>
                        <ul class="uk-list uk-list-divider uk-list-collapse">
                            <template x-for="relative in relatives" :key="relative.label + relative.node.id">
                                <li>
                                    <span class="uk-text-meta" x-text="relative.label"></span>
                                    <a href="#" @click.prevent="selectNode(relative.node)" x-text="relative.node.name"></a>
                                    <span class="uk-text-meta" x-show="relative.node.religion.slug !== selected.religion.slug" x-text="'· ' + relative.node.religion.name"></span>
                                    <div class="uk-text-small uk-text-muted" x-show="relative.tradition || relative.note">
                                        <span class="uk-label uk-label-warning explorer-religion-label" x-show="relative.tradition" x-text="relative.tradition + ' tradition'"></span>
                                        <span x-text="relative.note"></span>
                                    </div>
                                </li>
                            </template>
                        </ul>
                    </div>

                    <div class="uk-card-footer">
                        <button class="uk-button uk-button-primary uk-button-small" type="button" @click="focusOn(selected)" x-show="selected.id !== graph?.focus">
                            Centre tree here
                        </button>
                        <a class="uk-button uk-button-default uk-button-small" :href="editUrl" x-show="editUrl">
                            <span uk-icon="icon: pencil; ratio: 0.8"></span> Edit
                        </a>
                    </div>
                </div>
            </div>
        </template>

        {{-- Empty, loading and error states --}}
        <div class="uk-position-center uk-position-z-index explorer-panel" x-show="!graph && !loading && !error">
            <div class="uk-card uk-card-default uk-card-body uk-text-center">
                <span class="uk-text-primary" uk-icon="icon: git-branch; ratio: 2.5"></span>
                <h1 class="uk-card-title">Explore the family trees of myth</h1>
                <p class="uk-text-muted">Search for a god, hero or creature, or pick a religion to see its whole family.</p>
                @if ($religions->isNotEmpty())
                    <ul class="uk-subnav uk-subnav-pill uk-flex-center">
                        @foreach ($religions as $religion)
                            <li><a href="{{ route('religions.show', $religion) }}" @click.prevent="religion = @js($religion->slug); openReligion()">{{ $religion->name }}</a></li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>

        <div class="uk-position-center uk-position-z-index" x-show="loading" x-cloak>
            <div uk-spinner="ratio: 2"></div>
        </div>

        <div class="uk-position-center uk-position-z-index explorer-panel" x-show="error" x-cloak>
            <div class="uk-alert-danger" uk-alert>
                <p x-text="error"></p>
                <button class="uk-button uk-button-default uk-button-small" type="button" @click="reload">Try again</button>
            </div>
        </div>

        <div class="uk-position-center uk-position-z-index explorer-panel" x-show="graph && graph.nodes.length === 0" x-cloak>
            <div class="uk-card uk-card-default uk-card-body uk-text-center">
                <p class="uk-text-muted uk-margin-remove">This religion has no entities yet.</p>
            </div>
        </div>

        @can('access-admin')
            <a class="uk-button uk-button-default uk-button-small uk-position-bottom-right uk-position-small uk-position-z-index" href="{{ route('admin.dashboard') }}">
                <span uk-icon="icon: cog; ratio: 0.8"></span> Admin
            </a>
        @endcan
    </div>
@endsection

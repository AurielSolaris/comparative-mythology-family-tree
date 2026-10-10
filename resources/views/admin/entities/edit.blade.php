@extends('layouts.admin')

@section('title', $entity->name)

@section('content')
    <x-admin.page-header :title="$entity->name">
        <x-slot:subtitle>{{ $entity->religion->name }}@if ($entity->additionalReligions->isNotEmpty()) (also {{ $entity->additionalReligions->pluck('name')->join(', ') }})@endif @if ($entity->gender) · {{ $entity->gender->symbol() }} {{ $entity->gender->label() }}@endif @if ($entity->element) · {{ $entity->element }}@endif @if ($entity->region) · {{ $entity->region }}@endif</x-slot:subtitle>
        <x-slot:actions>
            <a class="uk-button uk-button-default" href="{{ route('entities.show', $entity) }}"><span uk-icon="git-branch"></span> View tree</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-grid-small" uk-grid>
        <div class="uk-width-3-5@m">
            <div class="uk-card uk-card-default">
                <div class="uk-card-header">
                    <h2 class="uk-card-title">Details</h2>
                </div>
                <div class="uk-card-body">
                    <form class="uk-form-stacked" method="POST" action="{{ route('admin.entities.update', $entity) }}">
                        @csrf
                        @method('PUT')
                        @include('admin.entities._form')

                        <div class="uk-margin-medium-top">
                            <button class="uk-button uk-button-primary" type="submit">Save</button>
                        </div>
                    </form>
                </div>
                <div class="uk-card-footer">
                    <form method="POST" action="{{ route('admin.entities.destroy', $entity) }}"
                          x-data="confirmedForm(@js("Delete {$entity->name} and all of its relations?"))"
                          @submit="confirmSubmit">
                        @csrf
                        @method('DELETE')
                        <button class="uk-button uk-button-text uk-text-danger" type="submit"><span uk-icon="trash"></span> Delete entity</button>
                    </form>
                </div>
            </div>

            <div class="uk-card uk-card-default uk-margin-top" id="sources">
                <div class="uk-card-header">
                    <h2 class="uk-card-title">Sources</h2>
                </div>
                <div class="uk-card-body">
                    @include('admin.sources._list', ['sources' => $entity->sources])
                </div>
                <div class="uk-card-footer">
                    <h3 class="uk-h5 uk-margin-small-top">Add a source</h3>
                    @include('admin.sources._create', ['action' => route('admin.entities.sources.store', $entity)])
                </div>
            </div>
        </div>

        <div class="uk-width-2-5@m">
            <div class="uk-card uk-card-default" id="relations">
                <div class="uk-card-header">
                    <h2 class="uk-card-title">Relations</h2>
                </div>

                <div class="uk-card-body">
                    @php($relatives = $entity->relatives())

                    @if ($relatives->isEmpty())
                        <p class="uk-text-muted">No relations yet.</p>
                    @else
                        <ul class="uk-list uk-list-divider">
                            @foreach ($relatives as $relative)
                                <li>
                                    <div class="uk-grid-small uk-flex-middle" uk-grid>
                                        <div class="uk-width-expand">
                                            <div class="uk-text-meta">{{ $relative['role']->label($entity->gender) }}</div>
                                            <a class="uk-link-heading" href="{{ route('admin.entities.edit', $relative['entity']) }}">{{ $relative['entity']->name }}</a>
                                            <span class="uk-text-meta">· {{ $relative['entity']->religion->name }}</span>
                                            @if ($relative['relation']->tradition || $relative['relation']->note)
                                                <div class="uk-text-small uk-text-muted">
                                                    @if ($relative['relation']->tradition)
                                                        <span class="uk-label uk-label-warning">{{ $relative['relation']->tradition->name }} tradition</span>
                                                    @endif
                                                    {{ $relative['relation']->note }}
                                                </div>
                                            @endif
                                            <a class="uk-text-small" href="{{ route('admin.relations.sources', $relative['relation']) }}">
                                                <span uk-icon="icon: link; ratio: 0.7"></span>
                                                {{ trans_choice('{0} Add sources|{1} 1 source|[2,*] :count sources', $relative['relation']->sources_count) }}
                                            </a>
                                        </div>
                                        <div class="uk-width-auto">
                                            <form method="POST" action="{{ route('admin.relations.destroy', $relative['relation']) }}"
                                                  x-data="confirmedForm(@js("Remove “{$entity->name}: {$relative['role']->label($entity->gender)} {$relative['entity']->name}”?"))"
                                                  @submit="confirmSubmit">
                                                @csrf
                                                @method('DELETE')
                                                <button class="uk-icon-button" type="submit" uk-icon="close" uk-tooltip="Remove relation" aria-label="Remove relation"></button>
                                            </form>
                                        </div>
                                    </div>
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                <div class="uk-card-footer">
                    <h3 class="uk-h5 uk-margin-small-top">Add a relation</h3>

                    <form class="uk-form-stacked" method="POST" action="{{ route('admin.entities.relations.store', $entity) }}"
                          x-data="entityPicker(@js(['suggestUrl' => route('search.suggestions'), 'excludeId' => $entity->id]))">
                        @csrf

                        <x-admin.field :label="$entity->name.' is…'" for="role">
                            <select @class(['uk-select', 'uk-form-danger' => $errors->has('role')]) id="role" name="role" required>
                                @foreach ($roles as $role)
                                    <option value="{{ $role->value }}" @selected(old('role') === $role->value)>{{ $role->label($entity->gender) }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>

                        <x-admin.field label="…this entity" for="related-entity-search" error="related_entity_id">
                            <div class="uk-inline uk-width-1-1" @click.outside="open = false">
                                <span class="uk-form-icon" uk-icon="search"></span>
                                <input @class(['uk-input', 'uk-form-danger' => $errors->has('related_entity_id')])
                                       id="related-entity-search" type="search" autocomplete="off" placeholder="Search by name or alias"
                                       x-model="query"
                                       @input.debounce.250ms="search"
                                       @keydown.arrow-down.prevent="move(1)"
                                       @keydown.arrow-up.prevent="move(-1)"
                                       @keydown.enter="if (open) { $event.preventDefault(); chooseHighlighted() }"
                                       @keydown.escape="open = false">
                                <input type="hidden" name="related_entity_id" :value="selectedId">

                                <div class="uk-drop uk-dropdown uk-width-1-1 uk-margin-small-top" :class="{ 'uk-open': open }">
                                    <ul class="uk-nav uk-dropdown-nav">
                                        <template x-for="(result, index) in results" :key="result.id">
                                            <li :class="{ 'uk-active': index === highlighted }">
                                                <a href="#" @click.prevent="choose(result)" @mouseenter="highlighted = index">
                                                    <span x-text="result.name"></span>
                                                    <div class="uk-nav-subtitle" x-text="result.religion"></div>
                                                </a>
                                            </li>
                                        </template>
                                        <li class="uk-nav-header" x-show="results.length === 0">No matches</li>
                                    </ul>
                                </div>
                            </div>
                        </x-admin.field>

                        <x-admin.field label="Tradition (optional)" for="relation-tradition" error="religion_id" help="Which pantheon or source this relation comes from.">
                            <select @class(['uk-select', 'uk-form-danger' => $errors->has('religion_id')]) id="relation-tradition" name="religion_id">
                                <option value="">Not specified</option>
                                @foreach ($religions as $religion)
                                    <option value="{{ $religion->id }}" @selected(old('religion_id') == $religion->id)>{{ $religion->name }}</option>
                                @endforeach
                            </select>
                        </x-admin.field>

                        <x-admin.field label="Note (optional)" for="note" help="e.g. “in Hesiod's Theogony”, “adoptive”, “in some traditions”.">
                            <input @class(['uk-input', 'uk-form-danger' => $errors->has('note')]) id="note" name="note" type="text" value="{{ old('note') }}" maxlength="255">
                        </x-admin.field>

                        <button class="uk-button uk-button-primary" type="submit" :disabled="!selectedId">Add relation</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endsection

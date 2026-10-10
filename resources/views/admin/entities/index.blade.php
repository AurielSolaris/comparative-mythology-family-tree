@extends('layouts.admin')

@section('title', 'Entities')

@section('content')
    <x-admin.page-header title="Entities">
        <x-slot:subtitle>{{ number_format($entities->total()) }} {{ Str::plural('entity', $entities->total()) }} · drag <span uk-icon="icon: table; ratio: 0.7"></span> to reorder</x-slot:subtitle>
        <x-slot:actions>
            <a class="uk-button uk-button-primary" href="{{ route('admin.entities.create', ['religion' => $filters['religion'] ?? null]) }}"><span uk-icon="plus"></span> Add entity</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-card uk-card-default">
        <div class="uk-card-header">
            <form method="GET" action="{{ route('admin.entities.index') }}" class="uk-grid-small" uk-grid>
                <div class="uk-width-expand@s">
                    <div class="uk-inline uk-width-1-1">
                        <span class="uk-form-icon" uk-icon="search"></span>
                        <input class="uk-input" type="search" name="q" value="{{ $filters['q'] ?? '' }}" placeholder="Name, alias, region or origin" aria-label="Search entities">
                    </div>
                </div>
                <div class="uk-width-1-4@s">
                    <select class="uk-select" name="religion" aria-label="Religion" onchange="this.form.requestSubmit()">
                        <option value="">All religions</option>
                        @foreach ($religions as $religion)
                            <option value="{{ $religion->id }}" @selected(($filters['religion'] ?? null) == $religion->id)>{{ $religion->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="uk-width-1-5@s">
                    <select class="uk-select" name="gender" aria-label="Gender" onchange="this.form.requestSubmit()">
                        <option value="">Any gender</option>
                        @foreach ($genders as $gender)
                            <option value="{{ $gender->value }}" @selected(($filters['gender'] ?? null) === $gender->value)>{{ $gender->symbol() }} {{ $gender->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="uk-width-auto@s">
                    <button class="uk-button uk-button-default" type="submit">Search</button>
                </div>
            </form>
        </div>

        @if ($entities->isEmpty())
            <div class="uk-card-body">
                <p class="uk-text-muted">
                    @if ($filters !== [])
                        No entities match. <a href="{{ route('admin.entities.index') }}">Clear the filters</a>
                    @else
                        No entities yet.
                    @endif
                </p>
            </div>
        @else
            <div class="uk-overflow-auto">
                <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-margin-remove">
                    <thead>
                        <tr>
                            <th class="uk-table-shrink"><span class="uk-hidden">Reorder</span></th>
                            <th class="uk-padding-small">Name</th>
                            <th>Religion</th>
                            <th class="uk-visible@s">Gender</th>
                            <th class="uk-visible@m">Region</th>
                            <th class="uk-table-shrink uk-text-nowrap">Relations</th>
                            <th class="uk-table-shrink"><span class="uk-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody uk-sortable="handle: .uk-sortable-handle"
                           x-data="sortableRows(@js(route('admin.entities.reorder')))"
                           @moved="save">
                        @foreach ($entities as $entity)
                            <tr data-id="{{ $entity->id }}">
                                <td class="uk-table-shrink">
                                    <span class="uk-sortable-handle uk-preserve-width uk-text-muted" uk-icon="table" uk-tooltip="Drag to reorder" aria-label="Drag to reorder"></span>
                                </td>
                                <td class="uk-padding-small">
                                    <a class="uk-link-heading" href="{{ route('admin.entities.edit', $entity) }}">{{ $entity->name }}</a>
                                    @if ($entity->aliases)
                                        <div class="uk-text-meta uk-text-truncate">{{ implode(', ', $entity->aliases) }}</div>
                                    @endif
                                </td>
                                <td>
                                    {{ $entity->religion->name }}
                                    @if ($entity->additionalReligions->isNotEmpty())
                                        <div class="uk-text-meta">also {{ $entity->additionalReligions->pluck('name')->join(', ') }}</div>
                                    @endif
                                </td>
                                <td class="uk-visible@s uk-text-muted">@if ($entity->gender){{ $entity->gender->symbol() }} {{ $entity->gender->label() }}@endif</td>
                                <td class="uk-visible@m uk-text-muted">{{ $entity->region }}</td>
                                <td>{{ $entity->outgoing_relations_count + $entity->incoming_relations_count }}</td>
                                <td class="uk-text-nowrap uk-padding-small">
                                    <a class="uk-icon-button" href="{{ route('entities.show', $entity) }}" uk-icon="git-branch" uk-tooltip="View tree"></a>
                                    <a class="uk-icon-button" href="{{ route('admin.entities.edit', $entity) }}" uk-icon="pencil" uk-tooltip="Edit"></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $entities->links() }}
@endsection

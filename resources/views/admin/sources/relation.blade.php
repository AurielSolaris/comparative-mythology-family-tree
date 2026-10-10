@extends('layouts.admin')

@php($title = "{$relation->entity->name}: {$relation->roleFor($relation->entity)->label($relation->entity->gender)} {$relation->relatedEntity->name}")

@section('title', "Sources · {$title}")

@section('content')
    <x-admin.page-header :title="$title">
        <x-slot:subtitle>
            Sources for this relation
            @if ($relation->tradition) · {{ $relation->tradition->name }} tradition @endif
            @if ($relation->note) · {{ $relation->note }} @endif
        </x-slot:subtitle>
        <x-slot:actions>
            <a class="uk-button uk-button-default" href="{{ route('admin.entities.edit', $relation->entity) }}#relations"><span uk-icon="arrow-left"></span> {{ $relation->entity->name }}</a>
            <a class="uk-button uk-button-default" href="{{ route('admin.entities.edit', $relation->relatedEntity) }}#relations"><span uk-icon="arrow-left"></span> {{ $relation->relatedEntity->name }}</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-grid-small" uk-grid>
        <div class="uk-width-3-5@m">
            <div class="uk-card uk-card-default" id="sources">
                <div class="uk-card-header">
                    <h2 class="uk-card-title">Sources</h2>
                </div>
                <div class="uk-card-body">
                    @include('admin.sources._list', ['sources' => $relation->sources])
                </div>
            </div>
        </div>

        <div class="uk-width-2-5@m">
            <div class="uk-card uk-card-default">
                <div class="uk-card-header">
                    <h2 class="uk-card-title">Add a source</h2>
                </div>
                <div class="uk-card-body">
                    @include('admin.sources._create', ['action' => route('admin.relations.sources.store', $relation)])
                </div>
            </div>
        </div>
    </div>
@endsection

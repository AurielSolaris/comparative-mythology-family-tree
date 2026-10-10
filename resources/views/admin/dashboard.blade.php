@extends('layouts.admin')

@section('title', 'Dashboard')

@section('content')
    <x-admin.page-header title="Dashboard">
        <x-slot:actions>
            <div class="uk-inline">
                <button class="uk-button uk-button-default" type="button"><span uk-icon="download"></span> Export</button>
                <div uk-dropdown="mode: click; pos: bottom-right">
                    <ul class="uk-nav uk-dropdown-nav">
                        <li class="uk-nav-header">All religions, entities and relations</li>
                        <li><a href="{{ route('export', 'json') }}" download>JSON (.json)</a></li>
                        <li><a href="{{ route('export', 'sqlite') }}" download>SQLite database (.sqlite)</a></li>
                        <li><a href="{{ route('export', 'db') }}" download>SQLite database (.db)</a></li>
                    </ul>
                </div>
            </div>
            <a class="uk-button uk-button-primary" href="{{ route('admin.entities.create') }}"><span uk-icon="plus"></span> Add entity</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-child-width-1-3@s uk-grid-match uk-grid-small" uk-grid>
        <div>
            <a class="uk-card uk-card-default uk-card-body uk-card-hover uk-link-toggle" href="{{ route('admin.entities.index') }}">
                <div class="uk-text-meta">Entities</div>
                <div class="uk-heading-small uk-margin-remove uk-text-primary">{{ number_format($entityCount) }}</div>
            </a>
        </div>
        <div>
            <a class="uk-card uk-card-default uk-card-body uk-card-hover uk-link-toggle" href="{{ route('admin.religions.index') }}">
                <div class="uk-text-meta">Religions</div>
                <div class="uk-heading-small uk-margin-remove uk-text-primary">{{ number_format($religionCount) }}</div>
            </a>
        </div>
        <div>
            <div class="uk-card uk-card-default uk-card-body">
                <div class="uk-text-meta">Relations</div>
                <div class="uk-heading-small uk-margin-remove uk-text-primary">{{ number_format($relationCount) }}</div>
            </div>
        </div>
    </div>

    <div class="uk-grid-small uk-margin-top" uk-grid>
        <div class="uk-width-2-3@m">
            <div class="uk-card uk-card-default">
                <div class="uk-card-header">
                    <h2 class="uk-card-title">Recently edited</h2>
                </div>
                @if ($recentEntities->isEmpty())
                    <div class="uk-card-body">
                        <p class="uk-text-muted">No entities yet. Start by adding a religion, then its entities.</p>
                    </div>
                @else
                    <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-table-small uk-margin-remove">
                        <tbody>
                            @foreach ($recentEntities as $entity)
                                <tr>
                                    <td class="uk-padding-small"><a class="uk-link-heading" href="{{ route('admin.entities.edit', $entity) }}">{{ $entity->name }}</a></td>
                                    <td class="uk-text-meta">{{ $entity->religion->name }}</td>
                                    <td class="uk-text-meta uk-text-right uk-padding-small">{{ $entity->updated_at->diffForHumans() }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </div>
        </div>

        <div class="uk-width-1-3@m">
            <div class="uk-card uk-card-default uk-card-body">
                <h2 class="uk-card-title">Religions</h2>
                @if ($religions->isEmpty())
                    <p class="uk-text-muted">No religions yet.</p>
                    <a class="uk-button uk-button-default" href="{{ route('admin.religions.create') }}">Add a religion</a>
                @else
                    <ul class="uk-list uk-list-divider">
                        @foreach ($religions as $religion)
                            <li>
                                <a class="uk-link-reset" href="{{ route('admin.entities.index', ['religion' => $religion->id]) }}">{{ $religion->name }}</a>
                                <span class="uk-badge uk-float-right">{{ $religion->memberCount() }}</span>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        </div>
    </div>
@endsection

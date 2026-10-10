@extends('layouts.admin')

@section('title', 'Religions')

@section('content')
    <x-admin.page-header title="Religions">
        <x-slot:actions>
            <a class="uk-button uk-button-primary" href="{{ route('admin.religions.create') }}"><span uk-icon="plus"></span> Add religion</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-card uk-card-default">
        @if ($religions->isEmpty())
            <div class="uk-card-body">
                <p class="uk-text-muted">No religions yet. Every entity belongs to one, so add a religion first.</p>
            </div>
        @else
            <div class="uk-overflow-auto">
                <table class="uk-table uk-table-divider uk-table-hover uk-table-middle uk-margin-remove">
                    <thead>
                        <tr>
                            <th class="uk-padding-small">Name</th>
                            <th class="uk-table-shrink uk-text-nowrap">Entities</th>
                            <th class="uk-table-shrink"><span class="uk-hidden">Actions</span></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($religions as $religion)
                            <tr>
                                <td class="uk-padding-small">
                                    @if ($religion->color)
                                        <span class="admin-color-swatch" style="background-color: {{ $religion->color }}" aria-hidden="true"></span>
                                    @endif
                                    <a class="uk-link-heading" href="{{ route('admin.religions.edit', $religion) }}">{{ $religion->name }}</a>
                                    @unless ($religion->color)
                                        <span class="uk-text-meta">· automatic colour</span>
                                    @endunless
                                </td>
                                <td>
                                    <a href="{{ route('admin.entities.index', ['religion' => $religion->id]) }}">{{ $religion->memberCount() }}</a>
                                    @if ($religion->additional_entities_count)
                                        <div class="uk-text-meta uk-text-nowrap">{{ $religion->additional_entities_count }} also elsewhere</div>
                                    @endif
                                </td>
                                <td class="uk-text-nowrap uk-padding-small">
                                    <a class="uk-icon-button" href="{{ route('religions.show', $religion) }}" uk-icon="git-branch" uk-tooltip="View tree"></a>
                                    <a class="uk-icon-button" href="{{ route('admin.entities.create', ['religion' => $religion->id]) }}" uk-icon="plus" uk-tooltip="Add entity"></a>
                                    <a class="uk-icon-button" href="{{ route('admin.religions.edit', $religion) }}" uk-icon="pencil" uk-tooltip="Edit"></a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    {{ $religions->links() }}
@endsection

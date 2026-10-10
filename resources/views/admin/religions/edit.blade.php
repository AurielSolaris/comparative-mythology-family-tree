@extends('layouts.admin')

@section('title', $religion->name)

@section('content')
    <x-admin.page-header :title="$religion->name">
        <x-slot:actions>
            <a class="uk-button uk-button-default" href="{{ route('religions.show', $religion) }}"><span uk-icon="git-branch"></span> View tree</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-card uk-card-default uk-card-body uk-width-xlarge@m">
        <form class="uk-form-stacked" method="POST" action="{{ route('admin.religions.update', $religion) }}">
            @csrf
            @method('PUT')
            @include('admin.religions._form')

            <div class="uk-margin-medium-top">
                <button class="uk-button uk-button-primary" type="submit">Save</button>
                <a class="uk-button uk-button-default" href="{{ route('admin.religions.index') }}">Cancel</a>
            </div>
        </form>

        <hr class="uk-divider-icon uk-margin-medium">

        <form method="POST" action="{{ route('admin.religions.destroy', $religion) }}"
              x-data="confirmedForm(@js("Delete {$religion->name}? This only works when no entity has it as primary religion."))"
              @submit="confirmSubmit">
            @csrf
            @method('DELETE')
            <button class="uk-button uk-button-danger" type="submit"><span uk-icon="trash"></span> Delete religion</button>
        </form>
    </div>
@endsection

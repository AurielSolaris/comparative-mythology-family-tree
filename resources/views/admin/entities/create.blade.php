@extends('layouts.admin')

@section('title', 'Add entity')

@section('content')
    <x-admin.page-header title="Add entity">
        <x-slot:subtitle>Relations can be added once the entity is saved.</x-slot:subtitle>
    </x-admin.page-header>

    @if ($religions->isEmpty())
        <div class="uk-alert-warning" uk-alert>
            <p>Every entity belongs to a religion. <a href="{{ route('admin.religions.create') }}">Add a religion</a> first.</p>
        </div>
    @else
        <div class="uk-card uk-card-default uk-card-body uk-width-2-3@m">
            <form class="uk-form-stacked" method="POST" action="{{ route('admin.entities.store') }}">
                @csrf
                @include('admin.entities._form')

                <div class="uk-margin-medium-top">
                    <button class="uk-button uk-button-primary" type="submit">Add entity</button>
                    <a class="uk-button uk-button-default" href="{{ route('admin.entities.index') }}">Cancel</a>
                </div>
            </form>
        </div>
    @endif
@endsection

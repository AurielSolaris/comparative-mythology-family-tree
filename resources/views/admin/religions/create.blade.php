@extends('layouts.admin')

@section('title', 'Add religion')

@section('content')
    <x-admin.page-header title="Add religion" />

    <div class="uk-card uk-card-default uk-card-body uk-width-xlarge@m">
        <form class="uk-form-stacked" method="POST" action="{{ route('admin.religions.store') }}">
            @csrf
            @include('admin.religions._form')

            <div class="uk-margin-medium-top">
                <button class="uk-button uk-button-primary" type="submit">Add religion</button>
                <a class="uk-button uk-button-default" href="{{ route('admin.religions.index') }}">Cancel</a>
            </div>
        </form>
    </div>
@endsection

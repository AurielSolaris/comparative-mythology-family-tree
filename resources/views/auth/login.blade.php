@extends('layouts.base')

@section('title', 'Log in')

@section('bodyClass', 'uk-background-muted')

@section('body')
    <div class="uk-section uk-flex uk-flex-middle uk-height-viewport">
        <div class="uk-container uk-width-1-1">
            <div class="uk-width-large uk-margin-auto">
                <div class="uk-card uk-card-default uk-card-body">
                    <h1 class="uk-card-title">Administrator log in</h1>

                    <form class="uk-form-stacked" method="POST" action="{{ route('login') }}">
                        @csrf

                        <x-admin.field label="Email" for="email">
                            <input @class(['uk-input', 'uk-form-danger' => $errors->has('email')]) id="email" name="email" type="email" value="{{ old('email') }}" required autofocus autocomplete="username">
                        </x-admin.field>

                        <x-admin.field label="Password" for="password">
                            <input @class(['uk-input', 'uk-form-danger' => $errors->has('password')]) id="password" name="password" type="password" required autocomplete="current-password">
                        </x-admin.field>

                        <div class="uk-margin">
                            <label><input class="uk-checkbox" type="checkbox" name="remember" value="1"> Remember me</label>
                        </div>

                        <button class="uk-button uk-button-primary uk-width-1-1" type="submit">Log in</button>
                    </form>
                </div>

                <p class="uk-text-center uk-text-small">
                    <a class="uk-link-muted" href="{{ route('explore') }}"><span uk-icon="arrow-left"></span> Back to the family trees</a>
                </p>
            </div>
        </div>
    </div>
@endsection

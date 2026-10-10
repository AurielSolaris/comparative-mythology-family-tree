@extends('layouts.base')

@php
    $navigation = [
        ['route' => 'admin.dashboard', 'active' => 'admin.dashboard', 'label' => 'Dashboard', 'icon' => 'home'],
        ['route' => 'admin.entities.index', 'active' => 'admin.entities.*', 'label' => 'Entities', 'icon' => 'users'],
        ['route' => 'admin.religions.index', 'active' => 'admin.religions.*', 'label' => 'Religions', 'icon' => 'world'],
    ];
@endphp

@section('bodyClass', 'uk-background-muted')

@section('body')
    <div class="uk-background-primary uk-light" uk-sticky>
        <div class="uk-container">
            <nav class="uk-navbar-container uk-navbar-transparent" uk-navbar>
                <div class="uk-navbar-left">
                    <a class="uk-navbar-item uk-logo" href="{{ route('admin.dashboard') }}">
                        <img class="app-logo uk-margin-small-right" src="{{ asset('favicon.svg') }}" width="32" height="32" alt="">
                        {{ config('app.name') }}
                    </a>
                    <ul class="uk-navbar-nav uk-visible@m">
                        @foreach ($navigation as $item)
                            <li @class(['uk-active' => request()->routeIs($item['active'])])>
                                <a href="{{ route($item['route']) }}">{{ $item['label'] }}</a>
                            </li>
                        @endforeach
                    </ul>
                </div>

                <div class="uk-navbar-right">
                    <ul class="uk-navbar-nav uk-visible@m">
                        <li>
                            <a href="#">{{ auth()->user()->name }} <span uk-navbar-parent-icon></span></a>
                            <div class="uk-navbar-dropdown">
                                <ul class="uk-nav uk-navbar-dropdown-nav">
                                    <li><a href="{{ route('explore') }}"><span uk-icon="git-branch"></span> View family trees</a></li>
                                    <li class="uk-nav-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('logout') }}">
                                            @csrf
                                            <button class="uk-button uk-button-link" type="submit"><span uk-icon="sign-out"></span> Log out</button>
                                        </form>
                                    </li>
                                </ul>
                            </div>
                        </li>
                    </ul>
                    <a class="uk-navbar-toggle uk-hidden@m" href="#admin-navigation" uk-navbar-toggle-icon uk-toggle aria-label="Menu"></a>
                </div>
            </nav>
        </div>
    </div>

    <div id="admin-navigation" uk-offcanvas="flip: true; overlay: true">
        <div class="uk-offcanvas-bar">
            <button class="uk-offcanvas-close" type="button" uk-close aria-label="Close menu"></button>
            <ul class="uk-nav uk-nav-default">
                <li class="uk-nav-header">{{ auth()->user()->name }}</li>
                @foreach ($navigation as $item)
                    <li @class(['uk-active' => request()->routeIs($item['active'])])>
                        <a href="{{ route($item['route']) }}"><span class="uk-margin-small-right" uk-icon="{{ $item['icon'] }}"></span>{{ $item['label'] }}</a>
                    </li>
                @endforeach
                <li class="uk-nav-divider"></li>
                <li><a href="{{ route('explore') }}"><span class="uk-margin-small-right" uk-icon="git-branch"></span>View family trees</a></li>
                <li>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="uk-button uk-button-link" type="submit"><span class="uk-margin-small-right" uk-icon="sign-out"></span>Log out</button>
                    </form>
                </li>
            </ul>
        </div>
    </div>

    <main class="uk-section uk-section-small">
        <div class="uk-container">
            @if (session('status'))
                <div class="uk-alert-success" uk-alert>
                    <a href="#" class="uk-alert-close" uk-close></a>
                    <p>{{ session('status') }}</p>
                </div>
            @endif

            @if (session('error'))
                <div class="uk-alert-danger" uk-alert>
                    <a href="#" class="uk-alert-close" uk-close></a>
                    <p>{{ session('error') }}</p>
                </div>
            @endif

            @yield('content')
        </div>
    </main>
@endsection

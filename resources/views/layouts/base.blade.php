<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">

        <title>@hasSection('title')@yield('title') · @endif{{ config('app.name') }}</title>
        <x-favicons />

        @fonts('instrument-sans')

        @vite(['resources/css/app.css', 'resources/js/app.ts'])
    </head>
    <body @hasSection('bodyClass') class="@yield('bodyClass')" @endif>
        @yield('body')
    </body>
</html>

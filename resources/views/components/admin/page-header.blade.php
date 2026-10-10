@props(['title'])

<div class="uk-grid-small uk-flex-middle uk-margin-medium-bottom" uk-grid>
    <div class="uk-width-expand">
        <h1 class="uk-h2 uk-margin-remove">{{ $title }}</h1>
        @isset($subtitle)
            <div class="uk-text-meta uk-margin-small-top">{{ $subtitle }}</div>
        @endisset
    </div>
    @isset($actions)
        <div class="uk-width-auto">
            {{ $actions }}
        </div>
    @endisset
</div>

@props(['label', 'for', 'error' => null, 'help' => null])

<div {{ $attributes->merge(['class' => 'uk-margin']) }}>
    <label class="uk-form-label" for="{{ $for }}">{{ $label }}</label>
    <div class="uk-form-controls">
        {{ $slot }}
    </div>
    @if ($help)
        <div class="uk-text-meta uk-margin-small-top">{{ $help }}</div>
    @endif
    @error($error ?? $for)
        <div class="uk-text-danger uk-text-small uk-margin-small-top">{{ $message }}</div>
    @enderror
</div>

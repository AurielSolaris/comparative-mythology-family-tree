{{-- Form to add a link or file source. Expects $action (the store URL). --}}
<form class="uk-form-stacked" method="POST" action="{{ $action }}" enctype="multipart/form-data"
      x-data="{ kind: @js(old('kind', App\Enums\SourceKind::Link->value)) }">
    @csrf

    <div class="uk-margin">
        <div class="uk-form-label">Type</div>
        <div class="uk-form-controls uk-grid-small uk-child-width-auto" uk-grid>
            @foreach (App\Enums\SourceKind::cases() as $kind)
                <label><input class="uk-radio" type="radio" name="kind" value="{{ $kind->value }}" x-model="kind"> {{ $kind->label() }}</label>
            @endforeach
        </div>
    </div>

    <div x-show="kind === 'link'">
        <x-admin.field label="Link" for="source-url" error="url">
            <input @class(['uk-input', 'uk-form-danger' => $errors->has('url')]) id="source-url" name="url" type="url" value="{{ old('url') }}" maxlength="2048" placeholder="https://…" :disabled="kind !== 'link'">
        </x-admin.field>
    </div>

    <div x-show="kind === 'file'" x-cloak>
        <x-admin.field label="File" for="source-file" error="file" :help="'Documents or images, up to '.App\Models\Source::maxUploadLabel().'.'">
            <div class="uk-width-1-1" uk-form-custom="target: true">
                <input id="source-file" name="file" type="file" accept="{{ collect(App\Models\Source::ALLOWED_EXTENSIONS)->map(fn ($extension) => '.'.$extension)->join(',') }}" :disabled="kind !== 'file'">
                <input @class(['uk-input', 'uk-form-danger' => $errors->has('file')]) type="text" tabindex="-1" placeholder="Choose a file…" aria-hidden="true">
            </div>
        </x-admin.field>
    </div>

    <x-admin.field label="Reference" for="source-ref" error="ref" help="The line, verse or page cited, e.g. “KTU 1.2 IV 11–12”.">
        <input @class(['uk-input', 'uk-form-danger' => $errors->has('ref')]) id="source-ref" name="ref" type="text" value="{{ old('ref') }}" maxlength="255">
    </x-admin.field>

    <button class="uk-button uk-button-default" type="submit"><span uk-icon="plus"></span> Add source</button>
</form>

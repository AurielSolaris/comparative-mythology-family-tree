@extends('layouts.admin')

@section('title', 'Edit source')

@section('content')
    <x-admin.page-header title="Edit source">
        <x-slot:subtitle>{{ $source->kind->label() }} · {{ Str::limit($source->label(), 80) }}</x-slot:subtitle>
        <x-slot:actions>
            <a class="uk-button uk-button-default" href="{{ $backUrl }}"><span uk-icon="arrow-left"></span> Back</a>
        </x-slot:actions>
    </x-admin.page-header>

    <div class="uk-card uk-card-default uk-width-2-3@m">
        <div class="uk-card-body">
            <form class="uk-form-stacked" method="POST" action="{{ route('admin.sources.update', $source) }}" enctype="multipart/form-data">
                @csrf
                @method('PUT')

                @if ($source->kind === App\Enums\SourceKind::Link)
                    <x-admin.field label="Link" for="source-url" error="url">
                        <input @class(['uk-input', 'uk-form-danger' => $errors->has('url')]) id="source-url" name="url" type="url" value="{{ old('url', $source->url) }}" maxlength="2048" required>
                    </x-admin.field>
                @else
                    <div class="uk-margin">
                        <div class="uk-form-label">Current file</div>
                        <div class="uk-form-controls">
                            @if ($source->hasStoredFile())
                                <a href="{{ route('admin.sources.file', $source) }}"><span uk-icon="download"></span> {{ $source->label() }}</a>
                            @else
                                {{ $source->label() }} <span class="uk-label uk-label-danger">file missing</span>
                            @endif
                        </div>
                    </div>

                    <x-admin.field label="Replace file" for="source-file" error="file" :help="'Leave empty to keep the current file. Documents or images, up to '.App\Models\Source::maxUploadLabel().'.'">
                        <div class="uk-width-1-1" uk-form-custom="target: true">
                            <input id="source-file" name="file" type="file" accept="{{ collect(App\Models\Source::ALLOWED_EXTENSIONS)->map(fn ($extension) => '.'.$extension)->join(',') }}">
                            <input @class(['uk-input', 'uk-form-danger' => $errors->has('file')]) type="text" tabindex="-1" placeholder="Choose a new file…" aria-hidden="true">
                        </div>
                    </x-admin.field>
                @endif

                <x-admin.field label="Reference" for="source-ref" error="ref" help="The line, verse or page cited, e.g. “KTU 1.2 IV 11–12”.">
                    <input @class(['uk-input', 'uk-form-danger' => $errors->has('ref')]) id="source-ref" name="ref" type="text" value="{{ old('ref', $source->ref) }}" maxlength="255">
                </x-admin.field>

                <div class="uk-margin-medium-top">
                    <button class="uk-button uk-button-primary" type="submit">Save</button>
                    <a class="uk-button uk-button-default" href="{{ $backUrl }}">Cancel</a>
                </div>
            </form>
        </div>
    </div>
@endsection

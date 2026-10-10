{{-- Sources of an entity or relation, each with edit and remove. Expects $sources. --}}
@if ($sources->isEmpty())
    <p class="uk-text-muted">No sources yet.</p>
@else
    <ul class="uk-list uk-list-divider">
        @foreach ($sources as $source)
            <li>
                <div class="uk-grid-small uk-flex-middle" uk-grid>
                    <div class="uk-width-auto">
                        <span class="uk-text-muted" uk-icon="{{ $source->kind === App\Enums\SourceKind::File ? 'file-text' : 'link' }}" uk-tooltip="{{ $source->kind->label() }}"></span>
                    </div>
                    <div class="uk-width-expand uk-text-break">
                        @if ($source->kind === App\Enums\SourceKind::File)
                            <a href="{{ route('admin.sources.file', $source) }}">{{ $source->label() }}</a>
                            @unless ($source->hasStoredFile())
                                <span class="uk-label uk-label-danger">file missing</span>
                            @endunless
                        @else
                            <a href="{{ $source->url }}" target="_blank" rel="noopener noreferrer">{{ Str::limit($source->label(), 80) }}</a>
                        @endif
                        @if ($source->ref)
                            <div class="uk-text-meta">{{ $source->ref }}</div>
                        @endif
                    </div>
                    <div class="uk-width-auto uk-text-nowrap">
                        <a class="uk-icon-button" href="{{ route('admin.sources.edit', $source) }}" uk-icon="pencil" uk-tooltip="Edit source" aria-label="Edit source"></a>
                        <form class="uk-display-inline" method="POST" action="{{ route('admin.sources.destroy', $source) }}"
                              x-data="confirmedForm(@js('Remove the source “'.Str::limit($source->label(), 60).'”?'))"
                              @submit="confirmSubmit">
                            @csrf
                            @method('DELETE')
                            <button class="uk-icon-button" type="submit" uk-icon="close" uk-tooltip="Remove source" aria-label="Remove source"></button>
                        </form>
                    </div>
                </div>
            </li>
        @endforeach
    </ul>
@endif

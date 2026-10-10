@if ($paginator->hasPages())
    <nav aria-label="Pagination">
        <ul class="uk-pagination uk-flex-center uk-margin-medium-top">
            @if ($paginator->onFirstPage())
                <li class="uk-disabled"><span><span uk-pagination-previous></span></span></li>
            @else
                <li><a href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Previous page"><span uk-pagination-previous></span></a></li>
            @endif

            @foreach ($elements as $element)
                @if (is_string($element))
                    <li class="uk-disabled"><span>{{ $element }}</span></li>
                @endif

                @if (is_array($element))
                    @foreach ($element as $page => $url)
                        @if ($page == $paginator->currentPage())
                            <li class="uk-active"><span aria-current="page">{{ $page }}</span></li>
                        @else
                            <li><a href="{{ $url }}">{{ $page }}</a></li>
                        @endif
                    @endforeach
                @endif
            @endforeach

            @if ($paginator->hasMorePages())
                <li><a href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Next page"><span uk-pagination-next></span></a></li>
            @else
                <li class="uk-disabled"><span><span uk-pagination-next></span></span></li>
            @endif
        </ul>
    </nav>
@endif

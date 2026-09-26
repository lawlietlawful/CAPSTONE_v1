{{--
    Compact pager for a dashboard card. Expects $paginator and $name (used only
    as a marker). Renders nothing for a plain collection or a single page.

    The links carry data-dash-page: the dashboard script swaps just the
    dashboard body for them (no full reload) and remembers the page across its
    20-second refresh; without JavaScript they are ordinary links.
--}}
@if(is_object($paginator) && method_exists($paginator, 'hasPages') && $paginator->hasPages())
    @php $last = $paginator->lastPage(); $current = $paginator->currentPage(); @endphp
    <div class="flex items-center justify-between gap-3 px-4 py-2.5 border-t border-gray-100 bg-gray-50/60" data-card-pager="{{ $name }}">
        <span class="text-[11px] text-gray-500">{{ $paginator->firstItem() }}&ndash;{{ $paginator->lastItem() }} of {{ $paginator->total() }}</span>

        <nav class="flex items-center gap-1" aria-label="{{ $name }} pages">
            @if($paginator->onFirstPage())
                <span class="w-6 h-6 inline-flex items-center justify-center rounded-md text-gray-300 cursor-not-allowed" aria-disabled="true"><i class="ti ti-chevron-left text-xs"></i></span>
            @else
                <a href="{{ $paginator->url($current - 1) }}" data-dash-page rel="prev" aria-label="Previous page"
                   class="w-6 h-6 inline-flex items-center justify-center rounded-md text-gray-500 hover:bg-white hover:text-gray-800 border border-transparent hover:border-gray-200 transition"><i class="ti ti-chevron-left text-xs"></i></a>
            @endif

            @if($last <= 5)
                @foreach(range(1, $last) as $page)
                    @if($page === $current)
                        <span class="min-w-[1.5rem] h-6 px-1.5 inline-flex items-center justify-center rounded-md bg-gray-900 text-white text-[11px] font-semibold" aria-current="page">{{ $page }}</span>
                    @else
                        <a href="{{ $paginator->url($page) }}" data-dash-page aria-label="Page {{ $page }}"
                           class="min-w-[1.5rem] h-6 px-1.5 inline-flex items-center justify-center rounded-md text-[11px] font-medium text-gray-600 hover:bg-white hover:text-gray-900 border border-transparent hover:border-gray-200 transition">{{ $page }}</a>
                    @endif
                @endforeach
            @else
                <span class="px-1.5 text-[11px] font-medium text-gray-600">{{ $current }} / {{ $last }}</span>
            @endif

            @if($paginator->hasMorePages())
                <a href="{{ $paginator->url($current + 1) }}" data-dash-page rel="next" aria-label="Next page"
                   class="w-6 h-6 inline-flex items-center justify-center rounded-md text-gray-500 hover:bg-white hover:text-gray-800 border border-transparent hover:border-gray-200 transition"><i class="ti ti-chevron-right text-xs"></i></a>
            @else
                <span class="w-6 h-6 inline-flex items-center justify-center rounded-md text-gray-300 cursor-not-allowed" aria-disabled="true"><i class="ti ti-chevron-right text-xs"></i></span>
            @endif
        </nav>
    </div>
@endif

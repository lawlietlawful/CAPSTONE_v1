{{--
    "Needs your attention" card for the Counselor and Admin dashboards.
    Expects $attentionTiles from App\Services\AttentionService::tiles().
    Each row links to a page whose list uses the same rule as the count.

    Only rows with something to do are shown (most urgent first, the order the
    service returns them in). When nothing needs attention the card says so in
    one line. h-full lets it match the height of a sibling card in the same row.
--}}
@if(!empty($attentionTiles))
@php
    $visibleTiles = collect($attentionTiles)->filter(fn ($t) => $t['count'] > 0)->values();
    $tone = [
        'red'   => ['row' => 'border-red-100 bg-red-50/60 hover:border-red-200',       'icon' => 'bg-red-100 text-red-600',     'count' => 'text-red-700'],
        'amber' => ['row' => 'border-amber-100 bg-amber-50/60 hover:border-amber-200', 'icon' => 'bg-amber-100 text-amber-600', 'count' => 'text-amber-700'],
        'blue'  => ['row' => 'border-blue-100 bg-blue-50/60 hover:border-blue-200',    'icon' => 'bg-blue-100 text-blue-600',   'count' => 'text-blue-700'],
    ];
@endphp
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6 h-full" data-attention-card data-attention-list>
    <h2 class="text-[15px] font-semibold text-gray-800 mb-4 flex items-center gap-2">
        <i class="ti ti-bell-ringing text-red-500"></i> Needs your attention
    </h2>

    @if($visibleTiles->isEmpty())
        <div class="flex flex-col items-center justify-center text-center py-6" data-attention-clear>
            <div class="w-12 h-12 bg-emerald-50 rounded-full flex items-center justify-center text-emerald-500 mb-3">
                <i class="ti ti-circle-check text-xl"></i>
            </div>
            <p class="text-sm font-medium text-gray-900">All clear</p>
            <p class="text-xs text-gray-500">Nothing needs attention right now.</p>
        </div>
    @else
        <div class="space-y-2">
            @foreach($visibleTiles as $tile)
                @php $t = $tone[$tile['tone']] ?? $tone['blue']; @endphp
                <a href="{{ $tile['url'] }}" data-attention="{{ $tile['key'] }}" title="{{ $tile['hint'] }}"
                   class="flex items-center gap-3 rounded-xl border px-3.5 py-3 transition {{ $t['row'] }}">
                    <span class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 {{ $t['icon'] }}">
                        <i class="ti {{ $tile['icon'] }} text-base"></i>
                    </span>
                    <span class="flex-1 min-w-0">
                        <span class="block text-sm font-semibold text-gray-900 leading-tight">{{ $tile['label'] }}</span>
                        <span class="block text-xs text-gray-500 leading-snug">{{ $tile['hint'] }}</span>
                    </span>
                    <span class="text-xl font-bold {{ $t['count'] }}">{{ number_format($tile['count']) }}</span>
                    <i class="ti ti-chevron-right text-gray-300 text-sm"></i>
                </a>
            @endforeach
        </div>
    @endif
</div>
@endif

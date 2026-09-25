{{--
    "Needs attention" strip shared by the Counselor and Admin dashboards.
    Expects $attentionTiles from App\Services\AttentionService::tiles().
    Each tile links to a page whose list uses the same rule as the count.
--}}
@if(!empty($attentionTiles))
@php
    $attentionTotal = collect($attentionTiles)->sum('count');
    $toneClasses = [
        'red'   => ['ring' => 'border-red-200 bg-red-50/60 hover:border-red-300',     'icon' => 'bg-red-100 text-red-600',     'count' => 'text-red-700'],
        'amber' => ['ring' => 'border-amber-200 bg-amber-50/60 hover:border-amber-300', 'icon' => 'bg-amber-100 text-amber-600', 'count' => 'text-amber-700'],
        'blue'  => ['ring' => 'border-blue-200 bg-blue-50/60 hover:border-blue-300',   'icon' => 'bg-blue-100 text-blue-600',   'count' => 'text-blue-700'],
    ];
@endphp

<div class="mb-6" data-attention-strip>
    <div class="flex items-center justify-between mb-2">
        <h2 class="text-sm font-bold text-gray-900 flex items-center gap-2">
            <i class="ti ti-bell-ringing text-red-500"></i> Needs your attention
        </h2>
        @if($attentionTotal === 0)
            <span class="text-xs font-medium text-emerald-600 flex items-center gap-1"><i class="ti ti-circle-check"></i> All clear</span>
        @endif
    </div>
    @php $cols = [4 => 'lg:grid-cols-4', 5 => 'lg:grid-cols-5', 6 => 'lg:grid-cols-6'][count($attentionTiles)] ?? 'lg:grid-cols-5'; @endphp
    <div class="grid grid-cols-2 {{ $cols }} gap-3">
        @foreach($attentionTiles as $tile)
            @php $active = $tile['count'] > 0; $t = $toneClasses[$tile['tone']] ?? $toneClasses['blue']; @endphp
            <a href="{{ $tile['url'] }}" data-attention="{{ $tile['key'] }}"
               class="rounded-xl border p-3 flex flex-col gap-1.5 transition {{ $active ? $t['ring'] : 'border-gray-100 bg-white hover:border-gray-200' }}"
               title="{{ $tile['hint'] }}">
                <div class="flex items-center justify-between">
                    <span class="w-8 h-8 rounded-lg flex items-center justify-center {{ $active ? $t['icon'] : 'bg-gray-100 text-gray-400' }}">
                        <i class="ti {{ $tile['icon'] }} text-base"></i>
                    </span>
                    <span class="text-2xl font-bold tracking-tight {{ $active ? $t['count'] : 'text-gray-300' }}">{{ number_format($tile['count']) }}</span>
                </div>
                <div>
                    <div class="text-[13px] font-semibold {{ $active ? 'text-gray-900' : 'text-gray-400' }}">{{ $tile['label'] }}</div>
                    <div class="text-[11px] text-gray-400 leading-snug">{{ $tile['hint'] }}</div>
                </div>
            </a>
        @endforeach
    </div>
</div>
@endif

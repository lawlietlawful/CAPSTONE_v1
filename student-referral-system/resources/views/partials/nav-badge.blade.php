{{-- Count pill for a sidebar link. Expects $count and $tone ('red'|'amber'); renders nothing at zero. --}}
@if(($count ?? 0) > 0)
    <span class="ml-auto min-w-[1.25rem] h-5 px-1.5 rounded-full text-[10px] font-bold flex items-center justify-center
                 {{ ($tone ?? 'amber') === 'red' ? 'bg-red-500 text-white' : 'bg-amber-400 text-amber-950' }}" data-nav-badge>
        {{ $count > 99 ? '99+' : $count }}
    </span>
@endif

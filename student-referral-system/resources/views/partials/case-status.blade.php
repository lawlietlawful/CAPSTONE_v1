{{--
    A student's single case status (see App\Support\CaseStatus). Expects
    $caseStatus. Optional $compact = true for the tighter At-Risk profile card.
--}}
@php
    $tones = [
        'red'   => 'border-red-200 bg-red-50 text-red-800',
        'amber' => 'border-amber-200 bg-amber-50 text-amber-800',
        'blue'  => 'border-blue-200 bg-blue-50 text-blue-800',
        'green' => 'border-emerald-200 bg-emerald-50 text-emerald-800',
        'gray'  => 'border-gray-200 bg-gray-50 text-gray-600',
    ];
    $toneClass = $tones[$caseStatus['tone']] ?? $tones['gray'];
@endphp
<div class="w-full rounded-xl border {{ $toneClass }} px-3 py-2.5 text-left {{ ($compact ?? false) ? 'mt-3' : 'mt-4' }}" data-case-status="{{ $caseStatus['key'] }}">
    <div class="flex items-center gap-2 text-sm font-bold">
        <i class="ti {{ $caseStatus['icon'] }} text-base"></i> {{ $caseStatus['label'] }}
    </div>
    <p class="text-xs mt-0.5 opacity-90 leading-snug">{{ $caseStatus['detail'] }}</p>
    @if($caseStatus['referral_id'])
        <a href="{{ route('admin.referrals.show', $caseStatus['referral_id']) }}" class="inline-block mt-1.5 text-[11px] font-semibold underline underline-offset-2 opacity-90 hover:opacity-100">
            View referral #{{ $caseStatus['referral_id'] }}
        </a>
    @endif
</div>

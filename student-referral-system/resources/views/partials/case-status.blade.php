{{--
    A student's single case status strip (see App\Support\CaseStatus). Expects
    $caseStatus. Slim like the safety flag: one heading line with the referral
    link on the right, one line of detail.
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
<div class="w-full rounded-lg border {{ $toneClass }} px-2.5 py-1.5 text-left mt-2" data-case-status="{{ $caseStatus['key'] }}">
    <div class="flex items-center gap-1.5">
        <i class="ti {{ $caseStatus['icon'] }} text-sm"></i>
        <span class="text-xs font-bold">{{ $caseStatus['label'] }}</span>
        @if($caseStatus['referral_id'])
            <a href="{{ route('admin.referrals.show', $caseStatus['referral_id']) }}" class="ml-auto text-[10px] font-semibold underline underline-offset-2 opacity-90 hover:opacity-100 whitespace-nowrap">
                View referral #{{ $caseStatus['referral_id'] }}
            </a>
        @endif
    </div>
    <p class="text-[11px] mt-0.5 opacity-90 leading-snug">{{ $caseStatus['detail'] }}</p>
</div>

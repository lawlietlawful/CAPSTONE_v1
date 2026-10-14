{{-- The referring teacher's after-resolution follow-up (Teacher Portal):
     how the student is doing in class now. --}}
@if($referral->teacher_followup_outcome)
    @php
        $tone = match ($referral->teacher_followup_outcome) {
            'improved' => ['bg-emerald-50 border-emerald-100', 'text-emerald-700', 'ti-trending-up'],
            'worse'    => ['bg-red-50 border-red-100', 'text-red-700', 'ti-trending-down'],
            default    => ['bg-gray-50 border-gray-100', 'text-gray-700', 'ti-minus'],
        };
    @endphp
    <div class="pt-4 border-t border-gray-100" data-teacher-followup>
        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Teacher's Follow-up</span>
        <div class="rounded-lg p-4 border {{ $tone[0] }}">
            <p class="flex items-center gap-1.5 text-sm font-semibold {{ $tone[1] }}">
                <i class="ti {{ $tone[2] }}"></i>
                {{ \App\Models\Referral::FOLLOWUP_OUTCOMES[$referral->teacher_followup_outcome] ?? $referral->teacher_followup_outcome }}
                <span class="font-normal text-xs text-gray-400">
                    · {{ $referral->referredBy?->name ?? 'Referring teacher' }}, {{ $referral->teacher_followup_at?->format('M j, Y') }}
                </span>
            </p>
            @if($referral->teacher_followup_note)
                <p class="mt-2 text-sm text-gray-700 leading-relaxed">{{ $referral->teacher_followup_note }}</p>
            @endif
        </div>
    </div>
@endif

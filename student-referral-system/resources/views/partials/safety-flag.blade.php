{{--
    Safety flag alert (see App\Support\SafetyFlags). Expects $safetyFlag
    (array or null); renders nothing when the student has no flag.
--}}
@if(!empty($safetyFlag))
    <div class="w-full rounded-xl border border-red-200 bg-red-50 px-3 py-2.5 text-left {{ ($compact ?? false) ? 'mt-3' : 'mt-4' }}" data-safety-flag>
        <div class="flex items-center gap-2 text-sm font-bold text-red-800">
            <i class="ti ti-alert-octagon text-base"></i> Safety flag
        </div>
        <p class="text-xs mt-0.5 text-red-800/90 leading-snug">
            {{ \App\Support\SafetyFlags::describe($safetyFlag) }}
            Review it before anything else.
        </p>
        <a href="{{ $safetyFlag['source'] === 'referral' ? route('admin.referrals.show', $safetyFlag['id']) : route('admin.behavioral-reports.show', $safetyFlag['id']) }}"
           class="inline-block mt-1.5 text-[11px] font-semibold text-red-700 underline underline-offset-2 hover:text-red-900">
            Open the {{ $safetyFlag['source'] }}
        </a>
    </div>
@endif

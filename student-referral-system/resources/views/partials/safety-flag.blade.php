{{--
    Safety flag strip (see App\Support\SafetyFlags). Expects $safetyFlag
    (array or null); renders nothing when the student has no flag. Kept slim
    - one heading line with the link on the right, one line of detail - so it
    can sit inside a profile card on the At-Risk profile and the Student page.
--}}
@if(!empty($safetyFlag))
    <div class="w-full rounded-lg border border-red-200 bg-red-50 px-2.5 py-1.5 text-left mt-2" data-safety-flag>
        <div class="flex items-center gap-1.5">
            <i class="ti ti-alert-octagon text-sm text-red-600"></i>
            <span class="text-xs font-bold text-red-800">Safety flag</span>
            <a href="{{ $safetyFlag['source'] === 'referral' ? route('admin.referrals.show', $safetyFlag['id']) : route('admin.behavioral-reports.show', $safetyFlag['id']) }}"
               class="ml-auto text-[10px] font-semibold text-red-700 underline underline-offset-2 hover:text-red-900 whitespace-nowrap">
                Open the {{ $safetyFlag['source'] }}
            </a>
        </div>
        <p class="text-[11px] mt-0.5 text-red-800/90 leading-snug">{{ \App\Support\SafetyFlags::describe($safetyFlag) }}</p>
    </div>
@endif

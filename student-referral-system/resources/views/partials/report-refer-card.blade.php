{{--
    Shown on a Behavioral Report page while the report has no referral. A
    report that did not auto-escalate used to be a dead end: nothing to attach
    interventions to. Expects $behavioral_report and $referRoute (the route
    name of the matching "refer" action).
--}}
@php
    $referCounselors = \App\Models\User::where('role', 'admin')->orderBy('name')->get();
    $canRefer = $behavioral_report->status !== 'resolved';
@endphp

<div class="pt-4 border-t border-gray-100" data-report-refer-card>
    <div class="rounded-xl border border-blue-100 bg-blue-50/50 p-4">
        <p class="text-sm font-semibold text-blue-900 flex items-center gap-2">
            <i class="ti ti-file-plus text-lg"></i> No referral yet for this report
        </p>
        @if($canRefer)
            <p class="text-xs text-blue-800 mt-1 leading-relaxed">
                This report did not escalate on its own. If it needs follow-up, open a referral: the student is risk-assessed,
                interventions can be logged against it, and this report follows the referral's status.
            </p>
            <form action="{{ route($referRoute, $behavioral_report->id) }}" method="POST" class="mt-3 flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-[200px]">
                    <label class="block text-[10px] font-bold text-blue-700 uppercase tracking-widest mb-1">Assign to</label>
                    <select name="counselor_id" class="w-full rounded-lg border border-blue-200 bg-white text-sm px-3 py-2">
                        <option value="">{{ auth()->user()->role === 'admin' ? 'Me' : 'Unassigned (a counselor will pick it up)' }}</option>
                        @foreach($referCounselors as $counselor)
                            @unless(auth()->user()->role === 'admin' && $counselor->id === auth()->id())
                                <option value="{{ $counselor->id }}">{{ $counselor->name }}</option>
                            @endunless
                        @endforeach
                    </select>
                </div>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5">
                    <i class="ti ti-file-plus"></i> Create referral from this report
                </button>
            </form>
        @else
            <p class="text-xs text-blue-800 mt-1">This report is resolved. Reopen it if it needs a referral.</p>
        @endif
    </div>
</div>

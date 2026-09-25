{{--
    "Referral" cell for a row in the Behavioral Reports list. Expects $report
    (with escalatedReferral loaded) and $area ('admin'|'counselor').

    Exactly one of three things, so every row lines up the same way:
      - the linked referral as a status-coloured pill (a report that escalated,
        or one a counselor already opened a case for),
      - a "Create referral" button while the report is open and has none,
      - a dash once the report is resolved without a referral.
--}}
@php $linked = $report->escalatedReferral; @endphp
<div class="flex items-center justify-center min-h-[1.75rem]">
    @if($linked)
        @php
            $refClass = match($linked->status) {
                'pending'     => 'bg-amber-50 text-amber-700 border-amber-200',
                'in_progress' => 'bg-blue-50 text-blue-700 border-blue-200',
                'resolved'    => 'bg-green-50 text-green-700 border-green-200',
                default       => 'bg-gray-100 text-gray-500 border-gray-200',
            };
        @endphp
        <a href="{{ route($area . '.referrals.show', $linked->id) }}" data-linked-referral
           class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-[11px] font-medium whitespace-nowrap hover:shadow-sm transition {{ $refClass }}"
           title="Open referral #{{ $linked->id }}">
            <i class="ti ti-file-text"></i> #{{ $linked->id }} <span class="opacity-40">&middot;</span> {{ ucwords(str_replace('_', ' ', $linked->status)) }}
        </a>
    @elseif($report->status !== 'resolved')
        <form action="{{ route($area . '.behavioral-reports.refer', $report->id) }}" method="POST" class="m-0"
              onsubmit="return confirm('Create a referral from this report?')" data-quick-refer>
            @csrf
            <button type="submit" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border border-emerald-200 bg-white text-emerald-700 text-[11px] font-medium whitespace-nowrap hover:bg-emerald-50 transition"
                    title="Create a referral from this report">
                <i class="ti ti-plus"></i> Create referral
            </button>
        </form>
    @else
        <span class="text-xs text-gray-300">&mdash;</span>
    @endif
</div>

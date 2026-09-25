{{--
    Action cell for a row in the Behavioral Reports list (Admin and Counselor).
    Expects $report and $area ('admin'|'counselor'). Just "view": the referral
    (linked pill or Create button) has its own column, so the icons in this
    column line up on every row (see partials.report-referral-cell).
--}}
<div class="flex items-center justify-center">
    <a href="{{ route($area . '.behavioral-reports.show', $report->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View Report">
        <i class="ti ti-eye"></i>
    </a>
</div>

@extends('layouts.counselor')

@section('title', 'Counselor Dashboard')
@section('page-title', 'Dashboard Overview')
@section('page-sub', 'Guidance & Counseling Office')

@section('content')

<div id="dashboard-body">
    @include('counselor.dashboard.partials.body')
</div>

@endsection

@push('styles')
<style>
    .risk-bar-low  { background: #16A34A; }
    .risk-bar-mod  { background: #D97706; }
    .risk-bar-high { background: #DC2626; }
</style>
@endpush

@push('scripts')
<script>
    (function () {
        const container = document.getElementById('dashboard-body');

        // Animate the risk bars in from zero, but only on a real page load —
        // the partial itself always renders its true width, so an AJAX swap
        // (see below) never needs this and never re-triggers it.
        function animateRiskBarsIn() {
            container.querySelectorAll('.risk-bar-low, .risk-bar-mod, .risk-bar-high').forEach(bar => {
                const target = bar.style.width;
                bar.style.width = '0%';
                requestAnimationFrame(() => requestAnimationFrame(() => { bar.style.width = target; }));
            });
        }
        animateRiskBarsIn();

        // Poll the same data this page was rendered with, so a referral
        // filed elsewhere (by a teacher, or another counselor claiming one)
        // shows up in these widgets without a manual refresh — the same
        // reason the header bell polls independently.
        function refreshDashboard() {
            fetch('{{ route('counselor.dashboard.refresh') }}', { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
                .then(r => {
                    // An expired session redirects to the login page, which fetch follows
                    // and reports as a normal 200 - never paste that into the dashboard.
                    if (r.redirected || !r.ok) {
                        if (r.redirected || r.status === 401 || r.status === 419) { window.location.reload(); }
                        return Promise.reject();
                    }
                    return r.text();
                })
                .then(html => { container.innerHTML = html; })
                .catch(() => {});
        }

        setInterval(refreshDashboard, 20000);
    })();
</script>
@endpush

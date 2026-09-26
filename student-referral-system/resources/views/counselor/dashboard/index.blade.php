@extends('layouts.counselor')

@section('title', 'Overview')
@section('page-title', 'Overview')
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

        // The two paged cards (?pending_page, ?watch_page) live in the URL. Keep them
        // across the refresh, and page in place without reloading the whole dashboard.
        const refreshUrl = '{{ route('counselor.dashboard.refresh') }}';
        let query = window.location.search;

        // Poll the same data this page was rendered with, so a referral
        // filed elsewhere (by a teacher, or another counselor claiming one)
        // shows up in these widgets without a manual refresh — the same
        // reason the header bell polls independently.
        function loadBody(search) {
            return fetch(refreshUrl + search, { headers: { 'Accept': 'text/html' }, credentials: 'same-origin' })
                .then(r => {
                    // An expired session redirects to the login page, which fetch follows
                    // and reports as a normal 200 - never paste that into the dashboard.
                    if (r.redirected || !r.ok) {
                        if (r.redirected || r.status === 401 || r.status === 419) { window.location.reload(); }
                        return Promise.reject();
                    }
                    return r.text();
                })
                .then(html => { container.innerHTML = html; });
        }

        function refreshDashboard() {
            loadBody(query).catch(() => {});
        }

        container.addEventListener('click', function (e) {
            const link = e.target.closest('a[data-dash-page]');
            if (!link) { return; }
            e.preventDefault();
            const search = new URL(link.href, window.location.origin).search;
            loadBody(search)
                .then(() => {
                    query = search;
                    window.history.replaceState(null, '', window.location.pathname + search);
                })
                .catch(() => { window.location.href = link.href; });
        });

        setInterval(refreshDashboard, 20000);
    })();
</script>
@endpush

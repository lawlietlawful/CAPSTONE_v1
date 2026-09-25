{{--
    The counselor's sidebar links, shared by layouts.counselor and (for the counselor role)
    layouts.admin - the two layouts each kept their own copy, so the sidebar differed by page
    and a link like Behavioral Reports led to a DIFFERENT screen depending on where you were.
    $navBadges is supplied by a view composer (App\Support\NavBadges).
--}}

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Main</p>

            <a href="{{ route('counselor.dashboard') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.dashboard') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-layout-dashboard text-base w-5"></i> Overview
            </a>

            <a href="{{ route('admin.risk.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.risk.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-alert-triangle text-base w-5"></i> At-Risk Students
                @include('partials.nav-badge', ['count' => $navBadges['risk'] ?? 0, 'tone' => 'red'])
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Counseling Services</p>

            <a href="{{ route('counselor.behavioral-reports.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.behavioral-reports.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-message-report text-base w-5"></i> Behavioral Reports
                @include('partials.nav-badge', ['count' => $navBadges['reports'] ?? 0, 'tone' => 'amber'])
            </a>

            <a href="{{ route('counselor.referrals.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.referrals.*') || request()->routeIs('counselor.referrals.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-file-text text-base w-5"></i> Referrals
                @include('partials.nav-badge', ['count' => $navBadges['referrals'] ?? 0, 'tone' => 'amber'])
            </a>

            <a href="{{ route('counselor.interventions.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.interventions.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-heart-handshake text-base w-5"></i> Interventions
                @include('partials.nav-badge', ['count' => $navBadges['interventions'] ?? 0, 'tone' => 'red'])
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Records</p>

            <a href="{{ route('admin.students.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.students.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-users text-base w-5"></i> Students
            </a>

            <p class="px-5 pt-4 pb-1 text-white/30 text-[10px] uppercase tracking-widest">Management</p>

            <a href="{{ route('admin.teachers.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.teachers.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-user-check text-base w-5"></i> Teachers
            </a>

            <a href="{{ route('admin.users.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.users.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-user-cog text-base w-5"></i> User Management
            </a>

            <a href="{{ route('admin.courses.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('admin.courses.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-books text-base w-5"></i> Courses &amp; Sections
            </a>

            <a href="{{ route('counselor.seminars.index') }}"
               class="nav-item flex items-center gap-2.5 px-3 mx-2 py-2.5 rounded-lg text-sm
                      {{ request()->routeIs('counselor.seminars.*') || request()->routeIs('admin.seminars.*') ? 'active text-white' : 'text-white/60' }}">
                <i class="ti ti-school text-base w-5"></i> Seminars
            </a>


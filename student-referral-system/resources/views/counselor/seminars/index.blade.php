@extends('layouts.counselor')

@section('title', 'Seminar Management')
@section('page-title', 'Seminar Management')
@section('page-sub', 'Manage proactive seminars, assign students, and track attendance')

@section('content')

@php
    // One palette for the status pills, the cards' date tiles, the calendar
    // events and its legend (the calendar's colours are in the styles below).
    $statusStyles = [
        'upcoming'  => ['label' => 'Upcoming',  'icon' => 'ti-calendar-clock', 'pill' => 'bg-blue-50 text-blue-700 border-blue-100',          'tile' => 'bg-blue-600',    'dot' => 'bg-blue-600'],
        'ongoing'   => ['label' => 'Ongoing',   'icon' => 'ti-player-play',    'pill' => 'bg-amber-50 text-amber-700 border-amber-100',       'tile' => 'bg-amber-500',   'dot' => 'bg-amber-500'],
        'completed' => ['label' => 'Completed', 'icon' => 'ti-circle-check',   'pill' => 'bg-emerald-50 text-emerald-700 border-emerald-100', 'tile' => 'bg-emerald-600', 'dot' => 'bg-emerald-500'],
        'cancelled' => ['label' => 'Cancelled', 'icon' => 'ti-circle-x',       'pill' => 'bg-red-50 text-red-700 border-red-100',             'tile' => 'bg-red-400',     'dot' => 'bg-red-400'],
    ];
    $filtersActive = collect(['search', 'status', 'is_required'])->contains(fn ($key) => request()->filled($key));
@endphp

<div x-data="{
        showCreateModal: {{ $errors->any() && !old('id') ? 'true' : 'false' }},
        viewMode: 'grid',
        init() {
            try { if (localStorage.getItem('seminars.view') === 'calendar') this.setView('calendar'); } catch (e) {}
        },
        setView(mode) {
            this.viewMode = mode;
            try { localStorage.setItem('seminars.view', mode); } catch (e) {}
            if (mode === 'calendar') this.$nextTick(() => initCalendar());
        }
     }">

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-3 mb-5">
    <a href="{{ route('counselor.seminars.index') }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition">
        <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
            <i class="ti ti-presentation text-gray-500 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalSeminars) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Total Seminars</div>
        </div>
    </a>
    <a href="{{ route('counselor.seminars.index', ['status' => 'upcoming']) }}" class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-blue-200 transition">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-calendar-event text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-blue-700 leading-none">{{ number_format($upcomingSessions) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Upcoming</div>
        </div>
    </a>
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-violet-50 flex items-center justify-center shrink-0">
            <i class="ti ti-users text-violet-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-violet-700 leading-none">{{ number_format($rosterEntries) }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">Students on Rosters</div>
        </div>
    </div>
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3" title="Attended out of everyone whose attendance was taken (attended or missed)">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="ti ti-user-check text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-emerald-700 leading-none">{{ $attendanceRate !== null ? $attendanceRate . '%' : '—' }}</div>
            <div class="text-[11px] font-medium text-gray-400 mt-0.5">
                Attendance Rate @if($attendanceTaken > 0)<span class="text-gray-300">·</span> {{ number_format($attendanceTaken) }} taken @endif
            </div>
        </div>
    </div>
</div>

{{-- ── Search and Filter Bar ─────────────────────────────────── --}}
<div class="mb-6">
    <form action="{{ route('counselor.seminars.index') }}" method="GET" class="w-full" id="filterForm">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
            <div class="flex-1 w-full relative">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search Seminars</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Title, venue or description..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
                </div>
            </div>

            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3 cursor-pointer">
                    <option value="">All Statuses</option>
                    @foreach($statusStyles as $value => $style)
                        <option value="{{ $value }}" @selected(request('status') === $value)>{{ $style['label'] }}</option>
                    @endforeach
                </select>
            </div>

            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Type</label>
                <select name="is_required" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3 cursor-pointer">
                    <option value="">All Types</option>
                    <option value="1" {{ request('is_required') === '1' ? 'selected' : '' }}>Required</option>
                    <option value="0" {{ request('is_required') === '0' ? 'selected' : '' }}>Optional</option>
                </select>
            </div>

            <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
                @if($filtersActive)
                    <a href="{{ route('counselor.seminars.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap h-[38px]">
                        <i class="ti ti-x"></i> Reset
                    </a>
                @endif
                <div class="bg-gray-100 p-1 rounded-lg flex items-center h-[38px]" role="group" aria-label="View">
                    <button type="button" @click="setView('grid')" :class="viewMode === 'grid' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:text-gray-700'" class="px-3 h-full text-xs font-semibold rounded-md transition flex items-center gap-1.5" data-view="grid">
                        <i class="ti ti-layout-grid text-sm"></i> Cards
                    </button>
                    <button type="button" @click="setView('calendar')" :class="viewMode === 'calendar' ? 'bg-white shadow-sm text-blue-600' : 'text-gray-500 hover:text-gray-700'" class="px-3 h-full text-xs font-semibold rounded-md transition flex items-center gap-1.5" data-view="calendar">
                        <i class="ti ti-calendar text-sm"></i> Calendar
                    </button>
                </div>
                <button type="button" @click="showCreateModal = true" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap h-[38px]">
                    <i class="ti ti-plus text-lg"></i> New Seminar
                </button>
            </div>
        </div>
    </form>
</div>

{{-- ── Cards View ─────────────────────────────────────────────── --}}
<div x-show="viewMode === 'grid'">
    @php $lastGroup = null; @endphp
    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-5">
    @forelse($seminars as $seminar)
        @php
            $style = $statusStyles[$seminar->status] ?? $statusStyles['upcoming'];
            $group = in_array($seminar->status, ['upcoming', 'ongoing'], true) ? 'ahead' : 'past';
            $time = \Carbon\Carbon::parse($seminar->time);
            $daysAway = (int) today()->diffInDays($seminar->date, false);
            $when = $group === 'ahead'
                ? match (true) { $daysAway <= 0 => 'Today', $daysAway === 1 => 'Tomorrow', default => "In {$daysAway} days" }
                : null;
            $audience = $seminar->target_course
                ? \App\Models\Course::abbreviate($seminar->target_course) . ($seminar->target_grade_level ? ' · ' . $seminar->target_grade_level : '')
                : ($seminar->target_grade_level ? 'All courses · ' . $seminar->target_grade_level : 'All students');
            $fillPct = $seminar->max_participants ? min(100, (int) round($seminar->students_count / $seminar->max_participants * 100)) : null;
        @endphp

        @if($group !== $lastGroup)
            <div class="col-span-full flex items-center gap-3 {{ $lastGroup ? 'mt-4' : '' }}" data-seminar-group="{{ $group }}">
                <h3 class="text-[11px] font-semibold uppercase tracking-wider text-gray-400 flex items-center gap-1.5">
                    <i class="ti {{ $group === 'ahead' ? 'ti-calendar-up' : 'ti-history' }}"></i>
                    {{ $group === 'ahead' ? 'Coming Up' : 'Past & Cancelled' }}
                </h3>
                <div class="flex-1 h-px bg-gray-200/70"></div>
            </div>
            @php $lastGroup = $group; @endphp
        @endif

        <article class="group bg-white border border-gray-100 rounded-2xl shadow-premium hover:shadow-hover hover:border-gray-200 transition-all duration-300 flex flex-col overflow-hidden" data-seminar-card>
            <div class="p-5 pb-4 flex gap-4">
                {{-- Date tile --}}
                <div class="w-14 shrink-0 rounded-xl overflow-hidden border border-gray-100 text-center bg-white shadow-sm self-start">
                    <div class="{{ $style['tile'] }} text-white text-[10px] font-bold uppercase tracking-wider py-1">{{ $seminar->date->format('M') }}</div>
                    <div class="text-xl font-bold text-gray-900 leading-none pt-1.5">{{ $seminar->date->format('d') }}</div>
                    <div class="text-[10px] font-medium text-gray-400 uppercase pt-0.5 pb-1.5">{{ $seminar->date->format('D') }}</div>
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-1.5 mb-1.5">
                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border {{ $style['pill'] }}">
                            <i class="ti {{ $style['icon'] }}"></i> {{ $style['label'] }}
                        </span>
                        @if($seminar->is_required)
                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[11px] font-medium border bg-rose-50 text-rose-700 border-rose-100">
                                <i class="ti ti-alert-circle"></i> Required
                            </span>
                        @else
                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[11px] font-medium border bg-gray-50 text-gray-500 border-gray-200">Optional</span>
                        @endif
                    </div>
                    <h3 class="font-semibold text-gray-900 text-[15px] leading-snug line-clamp-2" data-seminar-title>
                        <a href="{{ route('counselor.seminars.show', $seminar->id) }}" class="hover:text-blue-700 transition">{{ $seminar->title }}</a>
                    </h3>
                    <p class="text-xs text-gray-500 mt-1 flex items-center gap-1">
                        <i class="ti ti-clock text-gray-400"></i> {{ $time->format('h:i A') }}
                        @if($when)
                            <span class="text-gray-300">·</span> <span class="font-semibold text-blue-600">{{ $when }}</span>
                        @endif
                    </p>
                </div>
            </div>

            @if($seminar->description)
                <p class="px-5 text-sm text-gray-500 line-clamp-2 leading-relaxed">{{ $seminar->description }}</p>
            @endif

            <dl class="px-5 mt-3 space-y-1.5 text-[13px] text-gray-600">
                <div class="flex items-center gap-2 min-w-0">
                    <dt class="sr-only">Venue</dt>
                    <i class="ti ti-map-pin text-gray-400 shrink-0"></i>
                    <dd class="truncate">{{ $seminar->venue }}</dd>
                </div>
                <div class="flex items-center gap-2 min-w-0" @if($seminar->target_course) title="{{ $seminar->target_course }}" @endif>
                    <dt class="sr-only">Audience</dt>
                    <i class="ti ti-target-arrow text-gray-400 shrink-0"></i>
                    <dd class="truncate">{{ $audience }}</dd>
                </div>
                @if($seminar->trigger_reason)
                    <div class="flex items-center gap-2 min-w-0">
                        <dt class="sr-only">ML trigger</dt>
                        <i class="ti ti-sparkles text-violet-400 shrink-0"></i>
                        <dd class="truncate text-violet-700">{{ \App\Models\Seminar::TRIGGER_REASONS[$seminar->trigger_reason] ?? $seminar->trigger_reason }}</dd>
                    </div>
                @endif
            </dl>

            {{-- Roster --}}
            <div class="px-5 mt-4 mb-5">
                <div class="flex items-center justify-between text-xs mb-1.5">
                    <span class="font-medium text-gray-500 flex items-center gap-1"><i class="ti ti-users text-gray-400"></i> Roster</span>
                    <span class="font-semibold {{ $fillPct !== null && $fillPct >= 100 ? 'text-red-600' : 'text-gray-900' }}">
                        {{ $seminar->students_count }} <span class="font-normal text-gray-400">/ {{ $seminar->max_participants ?: '∞' }}</span>
                    </span>
                </div>
                <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                    @if($fillPct !== null)
                        <div class="{{ $fillPct >= 100 ? 'bg-red-500' : ($fillPct > 80 ? 'bg-amber-500' : 'bg-blue-500') }} h-1.5 rounded-full" style="width: {{ $fillPct }}%"></div>
                    @elseif($seminar->students_count > 0)
                        <div class="bg-blue-300 h-1.5 rounded-full w-full"></div>
                    @endif
                </div>
                @if($seminar->attended_count + $seminar->missed_count > 0)
                    <div class="flex items-center gap-3 mt-2 text-[11px] font-medium" data-attendance-summary>
                        <span class="text-emerald-600 flex items-center gap-1"><i class="ti ti-circle-check"></i> {{ $seminar->attended_count }} attended</span>
                        <span class="text-red-500 flex items-center gap-1"><i class="ti ti-circle-x"></i> {{ $seminar->missed_count }} missed</span>
                    </div>
                @endif
            </div>

            <div class="mt-auto px-5 py-3 border-t border-gray-100 bg-gray-50/60 flex items-center justify-between">
                <a href="{{ route('counselor.seminars.show', $seminar->id) }}" class="text-sm font-semibold text-blue-600 hover:text-blue-700 inline-flex items-center gap-1 group/link">
                    Manage Seminar <i class="ti ti-arrow-right transition-transform group-hover/link:translate-x-0.5"></i>
                </a>
                <div class="flex gap-1">
                    <button type="button" @click="$dispatch('open-edit-modal-{{ $seminar->id }}')" class="p-1.5 text-gray-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition" title="Edit">
                        <i class="ti ti-edit"></i>
                    </button>
                    <form id="delete-seminar-{{ $seminar->id }}" action="{{ route('counselor.seminars.destroy', $seminar->id) }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="$dispatch('open-confirm-modal', {
                                formId: 'delete-seminar-{{ $seminar->id }}',
                                title: 'Delete Seminar',
                                message: @js('Are you sure you want to delete "' . $seminar->title . '"?' . ($seminar->students_count > 0 ? ' Its roster of ' . $seminar->students_count . ' student' . ($seminar->students_count === 1 ? '' : 's') . ', with their attendance records, will be deleted too.' : '')),
                                confirmText: 'Yes, Delete Seminar'
                            })" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Delete">
                            <i class="ti ti-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </article>
    @empty
        <div class="col-span-full py-14 flex flex-col items-center justify-center text-center bg-white border border-gray-100 rounded-2xl shadow-premium">
            <div class="w-14 h-14 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-3">
                <i class="ti {{ $filtersActive ? 'ti-search-off' : 'ti-presentation' }} text-2xl"></i>
            </div>
            @if($filtersActive)
                <h3 class="text-sm font-semibold text-gray-900 mb-1">No seminars match your filters</h3>
                <p class="text-sm text-gray-500 mb-4">Try a different search, status or type.</p>
                <a href="{{ route('counselor.seminars.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition">Reset filters</a>
            @else
                <h3 class="text-sm font-semibold text-gray-900 mb-1">No seminars yet</h3>
                <p class="text-sm text-gray-500 mb-4">Create a seminar to start assigning students and tracking attendance.</p>
                <button type="button" @click="showCreateModal = true" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-1.5">
                    <i class="ti ti-plus"></i> Create Seminar
                </button>
            @endif
        </div>
    @endforelse
    </div>
    @if($seminars->hasPages())
        <div class="mt-6">
            {{ $seminars->links() }}
        </div>
    @endif
</div>

{{-- ── Calendar View ──────────────────────────────────────────── --}}
<div x-show="viewMode === 'calendar'" x-cloak class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="font-semibold text-gray-800 flex items-center gap-2"><i class="ti ti-calendar-month text-blue-600"></i> Seminar Calendar</h4>
            <p class="text-xs text-gray-400 mt-0.5">Every seminar, whatever the filters above. Click one to manage it.</p>
        </div>
        <div class="flex flex-wrap items-center gap-x-4 gap-y-1.5" data-calendar-legend>
            @foreach($statusStyles as $style)
                <span class="flex items-center gap-1.5 text-xs font-medium text-gray-500">
                    <span class="w-2.5 h-2.5 rounded-full {{ $style['dot'] }}"></span> {{ $style['label'] }}
                </span>
            @endforeach
        </div>
    </div>
    <div class="p-5">
        <div id="calendar" class="seminar-calendar w-full" style="min-height: 600px;"></div>
    </div>
</div>

<!-- Create Seminar Modal -->
<div x-show="showCreateModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
    <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
        <div x-show="showCreateModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showCreateModal = false"></div>

        <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

        <div x-show="showCreateModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
            <div class="flex items-center justify-between mb-5">
                <div>
                    <h3 class="text-xl font-bold text-gray-900">Create New Seminar</h3>
                    <p class="text-sm text-gray-500 mt-1">Schedule a proactive intervention seminar for students.</p>
                </div>
                <button type="button" @click="showCreateModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                    <i class="ti ti-x text-xl"></i>
                </button>
            </div>

            <form action="{{ route('counselor.seminars.store') }}" method="POST">
                @csrf
                @include('partials.seminar-form-fields', ['seminar' => null])

                <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100 bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-2xl">
                    <button type="button" @click="showCreateModal = false" class="px-5 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                        Cancel
                    </button>
                    <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                        <i class="ti ti-calendar-plus"></i> Create Seminar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Seminar Modals -->
@foreach($seminars as $seminar)
<div x-data="{ showEditModal: {{ $errors->any() && old('id') == $seminar->id ? 'true' : 'false' }} }" @open-edit-modal-{{ $seminar->id }}.window="showEditModal = true">
    <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showEditModal = false"></div>

            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>

            <div x-show="showEditModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-4xl p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Edit Seminar</h3>
                        <p class="text-sm text-gray-500 mt-1">Update details for '{{ $seminar->title }}'.</p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <form action="{{ route('counselor.seminars.update', $seminar->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $seminar->id }}">
                    @include('partials.seminar-form-fields', ['seminar' => $seminar])

                    <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100 bg-gray-50 -mx-8 -mb-8 px-8 py-4 rounded-b-2xl">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-semibold text-gray-600 hover:text-gray-900 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-6 py-2.5 text-sm font-semibold text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                            <i class="ti ti-device-floppy"></i> Update Seminar
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

</div>
@endsection

@push('styles')
<style>
    /* FullCalendar, restyled to match the rest of the app. */
    .seminar-calendar {
        --fc-border-color: #f1f5f9;
        --fc-today-bg-color: #f8fbff;
        --fc-neutral-bg-color: #f8fafc;
        --fc-page-bg-color: #ffffff;
        --fc-list-event-hover-bg-color: #f8fafc;
        font-size: 13px;
    }
    .seminar-calendar a { text-decoration: none; }
    .seminar-calendar .fc-header-toolbar.fc-toolbar { margin-bottom: 1rem; gap: .75rem; flex-wrap: wrap; }
    .seminar-calendar .fc-toolbar-title { font-size: 1.05rem; font-weight: 700; color: #111827; }
    .seminar-calendar .fc-button {
        background: #fff; border: 1px solid #e5e7eb; color: #4b5563;
        font-size: 12.5px; font-weight: 600; text-transform: none;
        padding: .4rem .8rem; border-radius: .5rem; box-shadow: 0 1px 2px rgba(0,0,0,.04);
    }
    .seminar-calendar .fc-button:hover { background: #f9fafb; color: #111827; border-color: #d1d5db; }
    .seminar-calendar .fc-button:focus, .seminar-calendar .fc-button:focus-visible { box-shadow: 0 0 0 3px rgba(59,130,246,.15) !important; outline: none; }
    .seminar-calendar .fc-button-primary:not(:disabled).fc-button-active,
    .seminar-calendar .fc-button-primary:not(:disabled):active { background: #2563eb; border-color: #2563eb; color: #fff; }
    .seminar-calendar .fc-button-primary:disabled { background: #f9fafb; color: #9ca3af; border-color: #e5e7eb; opacity: 1; }
    .seminar-calendar .fc-button-group > .fc-button { border-radius: 0; }
    .seminar-calendar .fc-button-group > .fc-button:first-child { border-top-left-radius: .5rem; border-bottom-left-radius: .5rem; }
    .seminar-calendar .fc-button-group > .fc-button:last-child { border-top-right-radius: .5rem; border-bottom-right-radius: .5rem; }
    .seminar-calendar .fc-today-button { margin-left: .5rem !important; }
    .seminar-calendar .fc-scrollgrid { border-radius: .75rem; overflow: hidden; border-color: #eef2f7; }
    .seminar-calendar .fc-col-header-cell { background: #f8fafc; }
    .seminar-calendar .fc-col-header-cell-cushion { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .05em; color: #6b7280; padding: .6rem 0; }
    .seminar-calendar .fc-daygrid-day-frame { min-height: 6.5rem; }
    .seminar-calendar .fc-daygrid-day-number { font-size: 12px; font-weight: 500; color: #4b5563; padding: .35rem .5rem; }
    .seminar-calendar .fc-day-other .fc-daygrid-day-number { color: #cbd5e1; }
    .seminar-calendar .fc-day-today .fc-daygrid-day-number {
        background: #2563eb; color: #fff; border-radius: 9999px; min-width: 1.6rem; height: 1.6rem;
        display: inline-flex; align-items: center; justify-content: center; padding: 0 .35rem; margin: .25rem;
    }
    .seminar-calendar .fc-daygrid-more-link { font-size: 11px; font-weight: 600; color: #2563eb; padding-left: 6px; }
    .seminar-calendar .fc-timegrid-slot-label-cushion, .seminar-calendar .fc-timegrid-axis-cushion { font-size: 11px; color: #9ca3af; }

    /* Events — same palette as the status pills. */
    .seminar-calendar .sem-ev {
        border: 0 !important; border-left: 3px solid !important; border-radius: .375rem;
        padding: 2px 6px; margin: 1px 4px 2px; font-size: 11.5px; font-weight: 500; cursor: pointer;
        transition: filter .15s ease;
    }
    .seminar-calendar .sem-ev:hover { filter: brightness(.96); }
    .seminar-calendar .sem-ev .fc-event-main, .seminar-calendar .sem-ev .fc-event-title, .seminar-calendar .sem-ev .fc-event-time { color: inherit; }
    .seminar-calendar .sem-ev .fc-event-time { font-weight: 700; margin-right: 4px; }
    .seminar-calendar .sem-ev-upcoming  { background: #eff6ff !important; border-left-color: #2563eb !important; color: #1d4ed8 !important; }
    .seminar-calendar .sem-ev-ongoing   { background: #fffbeb !important; border-left-color: #f59e0b !important; color: #b45309 !important; }
    .seminar-calendar .sem-ev-completed { background: #ecfdf5 !important; border-left-color: #10b981 !important; color: #047857 !important; }
    .seminar-calendar .sem-ev-cancelled { background: #fef2f2 !important; border-left-color: #f87171 !important; color: #b91c1c !important; }
    .seminar-calendar .sem-ev-cancelled .fc-event-title,
    .seminar-calendar .sem-ev-cancelled .sem-ev-name { text-decoration: line-through; }

    /* Month view: time on top, title below (ellipsized) — side by side they clipped each other. */
    .seminar-calendar .sem-ev-body { overflow: hidden; line-height: 1.3; padding: 1px 0; }
    .seminar-calendar .sem-ev-time { font-size: 10.5px; font-weight: 700; opacity: .8; }
    .seminar-calendar .sem-ev-name { font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }

    /* List view: plain rows with a coloured dot. */
    .seminar-calendar .fc-list { border-radius: .75rem; overflow: hidden; border-color: #eef2f7; }
    .seminar-calendar .fc-list-day-cushion { background: #f8fafc; font-size: 12px; font-weight: 600; color: #374151; }
    .seminar-calendar .fc-list-event.sem-ev { background: transparent !important; color: #374151 !important; border: 0 !important; margin: 0; }
    .seminar-calendar .fc-list-event-time { color: #6b7280; font-size: 12px; }
    .seminar-calendar .fc-list-event-title a { font-weight: 600; color: #111827; }
    .seminar-calendar .fc-list-event.sem-ev-upcoming  .fc-list-event-dot { border-color: #2563eb; }
    .seminar-calendar .fc-list-event.sem-ev-ongoing   .fc-list-event-dot { border-color: #f59e0b; }
    .seminar-calendar .fc-list-event.sem-ev-completed .fc-list-event-dot { border-color: #10b981; }
    .seminar-calendar .fc-list-event.sem-ev-cancelled .fc-list-event-dot { border-color: #f87171; }
    .seminar-calendar .fc-list-empty { background: #fff; color: #9ca3af; font-size: 13px; }
</style>
@endpush

@push('scripts')
<script src="{{ asset('vendor/fullcalendar.global.min.js') }}"></script>
<script>
    let calendarInitialized = false;
    function initCalendar() {
        if (calendarInitialized) {
            window.calendar.updateSize();
            return;
        }
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl || typeof FullCalendar === 'undefined') return;

        // Week view shows the working day (7am-7pm), stretched to fit any
        // seminar scheduled earlier or later so none is ever hidden.
        var seminarEvents = @json($calendarEvents);
        var startHours = seminarEvents.map(function (e) { return parseInt(e.start.substr(11, 2), 10); });
        var firstHour = Math.min.apply(null, [7].concat(startHours));
        var lastHour = Math.min(24, Math.max.apply(null, [19].concat(startHours.map(function (h) { return h + 2; }))));
        var pad = function (h) { return (h < 10 ? '0' : '') + h + ':00:00'; };

        window.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            views: {
                timeGridWeek: {
                    allDaySlot: false,
                    slotMinTime: pad(firstHour),
                    slotMaxTime: pad(lastHour),
                    dayHeaderFormat: { weekday: 'short', month: 'short', day: 'numeric' }
                }
            },
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek,listMonth'
            },
            buttonText: { today: 'Today', month: 'Month', week: 'Week', list: 'List' },
            events: seminarEvents,
            eventDisplay: 'block',
            dayMaxEvents: 3,
            fixedWeekCount: false,
            eventTimeFormat: { hour: 'numeric', minute: '2-digit', meridiem: 'short' },
            eventContent: function (arg) {
                if (arg.view.type !== 'dayGridMonth') return true;   // default rendering
                var body = document.createElement('div');
                body.className = 'sem-ev-body';
                var time = document.createElement('div');
                time.className = 'sem-ev-time';
                time.textContent = arg.timeText;
                var name = document.createElement('div');
                name.className = 'sem-ev-name';
                name.textContent = arg.event.title;
                body.append(time, name);
                return { domNodes: [body] };
            },
            noEventsContent: 'No seminars in this period',
            eventDidMount: function (info) {
                var p = info.event.extendedProps;
                info.el.title = info.event.title + '\n'
                    + p.status + (p.required ? ' · Required' : ' · Optional') + '\n'
                    + p.venue + '\n'
                    + p.enrolled + ' / ' + (p.capacity || '∞') + ' on the roster';
            },
            eventClick: function (info) {
                if (info.event.url) {
                    window.location.href = info.event.url;
                    info.jsEvent.preventDefault();
                }
            },
            height: 'auto'
        });
        window.calendar.render();
        calendarInitialized = true;
    }

    let searchTimeout = null;
    let searchInput = document.getElementById('searchInput');
    let filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function (e) {
            clearTimeout(searchTimeout);
            const val = e.target.value.trim();

            // Auto submit if cleared or if length >= 2
            if (val.length === 0 || val.length >= 2) {
                searchTimeout = setTimeout(() => {
                    filterForm.submit();
                }, 500); // Wait 500ms after user stops typing
            }
        });

        // Put cursor at the end of text when page reloads with search value
        if (searchInput.value) {
            const length = searchInput.value.length;
            searchInput.focus();
            searchInput.setSelectionRange(length, length);
        }
    }
</script>
@endpush

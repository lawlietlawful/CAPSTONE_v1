@extends('layouts.admin')

@section('title', 'Seminar Management')
@section('page-title', 'Seminar Management')
@section('page-sub', 'Manage proactive seminars, assign students, and track attendance')

@section('content')

<div x-data="{ showCreateModal: {{ $errors->any() && !old('id') ? 'true' : 'false' }}, viewMode: 'grid' }">

{{-- Summary Cards --}}
<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-3 gap-3 mb-5">
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-gray-300 transition">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-presentation text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalSeminars) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Seminars</div>
        </div>
    </div>
    
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-amber-200 transition">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
            <i class="ti ti-calendar-event text-amber-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-amber-700 leading-none">{{ number_format($upcomingSessions) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Upcoming Sessions</div>
        </div>
    </div>

    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3 hover:shadow-md hover:border-emerald-200 transition">
        <div class="w-10 h-10 rounded-lg bg-emerald-50 flex items-center justify-center shrink-0">
            <i class="ti ti-users text-emerald-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-emerald-700 leading-none">{{ number_format($totalStudentsEnrolled) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Students Enrolled</div>
        </div>
    </div>
</div>

{{-- Search and Filter Bar --}}
<div class="mb-6">
    <form action="{{ route('admin.seminars.index') }}" method="GET" class="w-full" id="filterForm">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
            <div class="flex-1 w-full relative">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search Seminars</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Search by title or venue..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
                </div>
            </div>

            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3 cursor-pointer">
                    <option value="">All Statuses</option>
                    <option value="upcoming" {{ request('status') == 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                    <option value="ongoing" {{ request('status') == 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Completed</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Cancelled</option>
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
                @if(request()->hasAny(['search', 'status', 'is_required']))
                    <a href="{{ route('admin.seminars.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                        <i class="ti ti-x"></i> Reset
                    </a>
                @endif
                <div class="bg-gray-100 p-1 rounded-lg flex items-center shadow-inner h-[38px] mr-2">
                    <button type="button" @click="viewMode = 'grid'" :class="viewMode === 'grid' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1 text-sm font-medium rounded-md transition-all h-full">
                        <i class="ti ti-layout-grid"></i>
                    </button>
                    <button type="button" @click="viewMode = 'calendar'; setTimeout(() => initCalendar(), 50);" :class="viewMode === 'calendar' ? 'bg-white shadow text-blue-600' : 'text-gray-500 hover:text-gray-700'" class="px-3 py-1 text-sm font-medium rounded-md transition-all h-full">
                        <i class="ti ti-calendar"></i>
                    </button>
                </div>
                <button type="button" @click="showCreateModal = true" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap h-[38px]">
                    <i class="ti ti-plus text-lg"></i> New Seminar
                </button>
            </div>
        </div>
    </form>
</div>

<div x-show="viewMode === 'grid'">
    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
    @forelse($seminars as $seminar)
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium flex flex-col overflow-hidden relative">
            @if($seminar->is_required)
                <div class="absolute top-0 right-0 bg-red-500 text-white text-[10px] font-bold px-2 py-1 rounded-bl-lg uppercase tracking-wider">Required</div>
            @endif

            <div class="p-5 border-b border-gray-50 flex-grow">
                <div class="flex items-center gap-2 mb-2">
                    @if($seminar->status == 'upcoming')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700"><i class="ti ti-calendar-clock"></i> Upcoming</span>
                    @elseif($seminar->status == 'ongoing')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-green-50 text-green-700"><i class="ti ti-player-play"></i> Ongoing</span>
                    @elseif($seminar->status == 'completed')
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700"><i class="ti ti-check"></i> Completed</span>
                    @else
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-red-50 text-red-700"><i class="ti ti-x"></i> Cancelled</span>
                    @endif
                </div>

                <h3 class="font-bold text-gray-900 text-lg leading-tight mb-1">{{ $seminar->title }}</h3>
                <p class="text-sm text-gray-500 mb-4 line-clamp-2">{{ $seminar->description }}</p>

                <div class="space-y-2 text-sm text-gray-600">
                    <div class="flex items-center gap-2">
                        <i class="ti ti-calendar text-gray-400"></i> {{ \Carbon\Carbon::parse($seminar->date)->format('M d, Y') }} at {{ \Carbon\Carbon::parse($seminar->time)->format('h:i A') }}
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-map-pin text-gray-400"></i> {{ $seminar->venue }}
                    </div>
                    <div class="flex items-center gap-2">
                        <i class="ti ti-target text-gray-400"></i> Target: {{ $seminar->target_course ?: 'All Courses' }} ({{ $seminar->target_grade_level ?: 'All Years' }})
                    </div>
                    <div class="mt-4 pt-4 border-t border-gray-50">
                        <div class="flex justify-between items-end mb-1.5">
                            <span class="text-[11px] font-bold text-gray-500 uppercase tracking-wider flex items-center gap-1.5">
                                <i class="ti ti-users text-gray-400"></i> Capacity
                            </span>
                            <span class="text-xs font-black {{ $seminar->max_participants && $seminar->total_attendees >= $seminar->max_participants ? 'text-red-500' : 'text-blue-600' }}">
                                {{ $seminar->total_attendees }} / {{ $seminar->max_participants ?: '∞' }}
                            </span>
                        </div>
                        @if($seminar->max_participants)
                            @php
                                $fillPct = min(100, round(($seminar->total_attendees / $seminar->max_participants) * 100));
                                $fillColor = $fillPct >= 100 ? 'bg-red-500' : ($fillPct > 80 ? 'bg-amber-500' : 'bg-blue-500');
                            @endphp
                            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div class="{{ $fillColor }} h-1.5 rounded-full transition-all duration-500" style="width: {{ $fillPct }}%"></div>
                            </div>
                        @else
                            <div class="w-full bg-gray-100 rounded-full h-1.5 overflow-hidden">
                                <div class="bg-emerald-400 h-1.5 rounded-full w-full"></div>
                            </div>
                        @endif
                    </div>
                </div>
            </div>

            <div class="px-5 py-3 bg-gray-50/50 flex justify-between items-center mt-auto">
                <a href="{{ route('admin.seminars.show', $seminar->id) }}" class="text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline flex items-center gap-1">
                    Manage Participants <i class="ti ti-arrow-right"></i>
                </a>
                <div class="flex gap-2">
                    <button type="button" @click="$dispatch('open-edit-modal-{{ $seminar->id }}')" class="p-1.5 text-gray-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition" title="Edit">
                        <i class="ti ti-edit"></i>
                    </button>
                    <form id="delete-seminar-{{ $seminar->id }}" action="{{ route('admin.seminars.destroy', $seminar->id) }}" method="POST" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="button" @click="$dispatch('open-confirm-modal', { 
                                formId: 'delete-seminar-{{ $seminar->id }}', 
                                title: 'Delete Seminar', 
                                message: 'Are you sure you want to delete the seminar \'{{ addslashes($seminar->title) }}\'?',
                                confirmText: 'Yes, Delete Seminar'
                            })" class="p-1.5 text-gray-400 hover:text-red-500 hover:bg-red-50 rounded-lg transition" title="Delete">
                            <i class="ti ti-trash"></i>
                        </button>
                    </form>
                </div>
            </div>
        </div>
    @empty
        <div class="col-span-full py-12 flex flex-col items-center justify-center text-center bg-white border border-gray-100 rounded-2xl shadow-sm">
            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-400 mb-3">
                <i class="ti ti-presentation text-2xl"></i>
            </div>
            <h3 class="text-lg font-medium text-gray-900 mb-1">No Seminars Found</h3>
            <p class="text-gray-500 mb-4">Get started by creating a new seminar for student intervention.</p>
            <button type="button" @click="showCreateModal = true" class="px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm">
                Create Seminar
            </button>
        </div>
    @endforelse
    @if($seminars->hasPages())
        <div class="mt-6">
            {{ $seminars->links() }}
        </div>
    @endif
</div>

<!-- Calendar View -->
<div x-show="viewMode === 'calendar'" x-cloak class="bg-white border border-gray-100 rounded-2xl shadow-premium p-6">
    <div id="calendar" class="w-full" style="min-height: 600px;"></div>
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

            <form action="{{ route('admin.seminars.store') }}" method="POST">
                @csrf
                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                    
                    <!-- Left Column -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <i class="ti ti-calendar-event text-blue-600"></i> Seminar Details
                        </h4>
                        
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Seminar Title <span class="text-red-500">*</span></label>
                            <input type="text" name="title" value="{{ old('title') }}" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('title') border-red-500 @enderror">
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Date <span class="text-red-500">*</span></label>
                                <input type="date" name="date" value="{{ old('date') }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Time <span class="text-red-500">*</span></label>
                                <input type="time" name="time" value="{{ old('time') }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Venue / Room <span class="text-red-500">*</span></label>
                            <input type="text" name="venue" value="{{ old('venue') }}" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">ML Trigger Reason <span class="text-red-500">*</span></label>
                            <select name="trigger_reason" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                <option value="general" {{ old('trigger_reason') === 'general' ? 'selected' : '' }}>General Guidance</option>
                                <option value="academic_recovery" {{ old('trigger_reason') === 'academic_recovery' ? 'selected' : '' }}>Academic Recovery</option>
                                <option value="attendance_intervention" {{ old('trigger_reason') === 'attendance_intervention' ? 'selected' : '' }}>Attendance Intervention</option>
                                <option value="behavioral_counseling" {{ old('trigger_reason') === 'behavioral_counseling' ? 'selected' : '' }}>Behavioral Counseling</option>
                                <option value="anti_bullying" {{ old('trigger_reason') === 'anti_bullying' ? 'selected' : '' }}>Anti-Bullying</option>
                                <option value="values_formation" {{ old('trigger_reason') === 'values_formation' ? 'selected' : '' }}>Values Formation</option>
                                <option value="orientation" {{ old('trigger_reason') === 'orientation' ? 'selected' : '' }}>Orientation</option>
                            </select>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                                <select name="status" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="upcoming" {{ old('status', 'upcoming') === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                                    <option value="ongoing" {{ old('status') === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                    <option value="completed" {{ old('status') === 'completed' ? 'selected' : '' }}>Completed</option>
                                    <option value="cancelled" {{ old('status') === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                                <select name="is_required" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="1" {{ old('is_required', '1') == '1' ? 'selected' : '' }}>Required</option>
                                    <option value="0" {{ old('is_required') === '0' ? 'selected' : '' }}>Optional</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column -->
                    <div class="space-y-4">
                        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                            <i class="ti ti-users text-blue-600"></i> Audience & Additional
                        </h4>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Target Course</label>
                                <input type="text" name="target_course" value="{{ old('target_course') }}" placeholder="e.g. BSIT"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Target Year</label>
                                <select name="target_grade_level"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="">All Years</option>
                                    <option value="1st Year">1st Year</option>
                                    <option value="2nd Year">2nd Year</option>
                                    <option value="3rd Year">3rd Year</option>
                                    <option value="4th Year">4th Year</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4">
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Speaker</label>
                                <input type="text" name="speaker" value="{{ old('speaker') }}"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Max Capacity</label>
                                <input type="number" name="max_participants" value="{{ old('max_participants') }}" placeholder="Leave blank for unlimited"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                            <textarea name="description" rows="3" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ old('description') }}</textarea>
                        </div>
                    </div>
                </div>

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

                <form action="{{ route('admin.seminars.update', $seminar->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="id" value="{{ $seminar->id }}">
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar">
                        
                        <!-- Left Column -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-calendar-event text-blue-600"></i> Seminar Details
                            </h4>
                            
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Seminar Title <span class="text-red-500">*</span></label>
                                <input type="text" name="title" value="{{ old('title', $seminar->title) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Date <span class="text-red-500">*</span></label>
                                    <input type="date" name="date" value="{{ old('date', $seminar->date->format('Y-m-d')) }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Time <span class="text-red-500">*</span></label>
                                    <input type="time" name="time" value="{{ old('time', \Carbon\Carbon::parse($seminar->time)->format('H:i')) }}" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Venue / Room <span class="text-red-500">*</span></label>
                                <input type="text" name="venue" value="{{ old('venue', $seminar->venue) }}" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                                    <select name="status" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="upcoming" {{ old('status', $seminar->status) === 'upcoming' ? 'selected' : '' }}>Upcoming</option>
                                        <option value="ongoing" {{ old('status', $seminar->status) === 'ongoing' ? 'selected' : '' }}>Ongoing</option>
                                        <option value="completed" {{ old('status', $seminar->status) === 'completed' ? 'selected' : '' }}>Completed</option>
                                        <option value="cancelled" {{ old('status', $seminar->status) === 'cancelled' ? 'selected' : '' }}>Cancelled</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                                    <select name="is_required" required
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="1" {{ old('is_required', $seminar->is_required) == 1 ? 'selected' : '' }}>Required</option>
                                        <option value="0" {{ old('is_required', $seminar->is_required) == 0 ? 'selected' : '' }}>Optional</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <!-- Right Column -->
                        <div class="space-y-4">
                            <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
                                <i class="ti ti-users text-blue-600"></i> Audience & Additional
                            </h4>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Target Course</label>
                                    <input type="text" name="target_course" value="{{ old('target_course', $seminar->target_course) }}" placeholder="e.g. BSIT"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Target Year</label>
                                    <select name="target_grade_level"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="">All Years</option>
                                        <option value="1st Year" {{ old('target_grade_level', $seminar->target_grade_level) == '1st Year' ? 'selected' : '' }}>1st Year</option>
                                        <option value="2nd Year" {{ old('target_grade_level', $seminar->target_grade_level) == '2nd Year' ? 'selected' : '' }}>2nd Year</option>
                                        <option value="3rd Year" {{ old('target_grade_level', $seminar->target_grade_level) == '3rd Year' ? 'selected' : '' }}>3rd Year</option>
                                        <option value="4th Year" {{ old('target_grade_level', $seminar->target_grade_level) == '4th Year' ? 'selected' : '' }}>4th Year</option>
                                    </select>
                                </div>
                            </div>

                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Speaker</label>
                                    <input type="text" name="speaker" value="{{ old('speaker', $seminar->speaker) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1">Max Capacity</label>
                                    <input type="number" name="max_participants" value="{{ old('max_participants', $seminar->max_participants) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                </div>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
                                <textarea name="description" rows="3" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ old('description', $seminar->description) }}</textarea>
                            </div>
                        </div>
                    </div>

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

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
    let calendarInitialized = false;
    function initCalendar() {
        if (calendarInitialized) {
            window.calendar.updateSize();
            return;
        }
        var calendarEl = document.getElementById('calendar');
        if (!calendarEl) return;
        
        window.calendar = new FullCalendar.Calendar(calendarEl, {
            initialView: 'dayGridMonth',
            headerToolbar: {
                left: 'prev,next today',
                center: 'title',
                right: 'dayGridMonth,timeGridWeek'
            },
            events: {!! json_encode($calendarEvents) !!},
            eventClick: function(info) {
                if(info.event.url) {
                    window.location.href = info.event.url;
                    info.jsEvent.preventDefault();
                }
            },
            height: 'auto',
            themeSystem: 'standard'
        });
        window.calendar.render();
        calendarInitialized = true;
    }

    let searchTimeout = null;
    let searchInput = document.getElementById('searchInput');
    let filterForm = document.getElementById('filterForm');

    if (searchInput && filterForm) {
        searchInput.addEventListener('input', function(e) {
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

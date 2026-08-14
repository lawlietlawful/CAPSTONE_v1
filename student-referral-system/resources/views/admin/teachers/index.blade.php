@extends('layouts.admin')

@section('title', 'Teachers')
@section('page-title', 'Teacher Directory')
@section('page-sub', 'View all teacher accounts and their activity within the system')

@section('content')

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-5">
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
            <i class="ti ti-user-check text-blue-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalTeachers) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Registered Teachers</div>
        </div>
    </div>
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center gap-3">
        <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
            <i class="ti ti-message-report text-amber-600 text-lg"></i>
        </div>
        <div>
            <div class="text-lg font-bold text-amber-700 leading-none">{{ number_format($totalBehavioralReports) }}</div>
            <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Behavioral Reports</div>
        </div>
    </div>
</div>

<div class="mb-6">
    <form method="GET" action="{{ route('admin.teachers.index') }}" id="filterForm" class="w-full">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
            <div class="flex-1 w-full relative">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search Teacher</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Name, email, or username..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
                </div>
            </div>

            <div class="w-full lg:w-48">
                <label class="block text-xs font-medium text-gray-500 mb-1">Sort By</label>
                <select name="sort" onchange="document.getElementById('filterForm').submit();" class="block w-full border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition py-2 px-3">
                    <option value="name_asc" {{ request('sort') === 'name_asc' ? 'selected' : '' }}>Name (A-Z)</option>
                    <option value="name_desc" {{ request('sort') === 'name_desc' ? 'selected' : '' }}>Name (Z-A)</option>
                    <option value="newest" {{ request('sort') === 'newest' ? 'selected' : '' }}>Newest Registered</option>
                    <option value="oldest" {{ request('sort') === 'oldest' ? 'selected' : '' }}>Oldest Registered</option>
                </select>
            </div>

            <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
                <a href="{{ route('admin.teachers.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-x"></i> Reset
                </a>
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-search"></i> Search
                </button>
                <a href="{{ route('admin.teachers.export') }}" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-download"></i> Export CSV
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ── Teachers Table ────────────────────────────────────────── --}}
<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Teacher</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Employee ID</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Reports Filed</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Engagement</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider">Joined</th>
                    <th class="px-5 py-3 font-semibold text-gray-600 text-xs uppercase tracking-wider text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 text-sm">
                @forelse($teachers as $teacher)
                    <tr class="hover:bg-gray-50/50 transition">
                        <td class="px-5 py-3">
                            <div class="flex items-center gap-3">
                                <div class="w-9 h-9 rounded-full bg-blue-100 flex items-center justify-center text-blue-700 font-bold text-sm flex-shrink-0">
                                    {{ strtoupper(substr($teacher->name, 0, 1)) }}
                                </div>
                                <div>
                                    <a href="{{ route('admin.teachers.show', $teacher->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition">{{ $teacher->name }}</a>
                                    <p class="text-xs text-gray-500">{{ $teacher->email }}</p>
                                </div>
                            </div>
                        </td>
                        <td class="px-5 py-3 text-gray-600">
                            <span class="inline-flex items-center px-2 py-0.5 rounded bg-gray-100 text-gray-700 text-xs font-mono">
                                {{ $teacher->username ?? '—' }}
                            </span>
                            @unless($teacher->account_activated_at)
                                <span class="mt-1 flex w-max items-center gap-1 px-2 py-0.5 rounded text-[11px] font-medium bg-indigo-50 text-indigo-700 border border-indigo-100" title="This teacher hasn't set their password yet">
                                    <i class="ti ti-key text-[11px]"></i> Pending activation
                                </span>
                            @endunless
                        </td>
                        <td class="px-5 py-3 text-center">
                            <span class="inline-flex items-center gap-1 text-sm font-medium {{ $teacher->behavioral_reports_count > 0 ? 'text-amber-700' : 'text-gray-400' }}">
                                <i class="ti ti-message-report text-xs"></i> {{ number_format($teacher->behavioral_reports_count) }}
                            </span>
                        </td>
                        <td class="px-5 py-3">
                            @if($teacher->engagement_status === 'highly_active')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200">
                                    <i class="ti ti-bolt mr-1"></i> Highly Active
                                </span>
                            @elseif($teacher->engagement_status === 'active')
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-amber-100 text-amber-800 border border-amber-200">
                                    <i class="ti ti-activity mr-1"></i> Active
                                </span>
                            @else
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-50 text-red-700 border border-red-200">
                                    <i class="ti ti-moon mr-1"></i> Inactive
                                </span>
                            @endif
                        </td>
                        <td class="px-5 py-3 text-xs text-gray-500">{{ $teacher->created_at->format('M d, Y') }}</td>
                        <td class="px-5 py-3 text-center relative" x-data="{ open: false }">
                            <button @click="open = !open" @click.away="open = false" class="text-gray-400 hover:text-gray-600 p-1 rounded-md hover:bg-gray-100 transition focus:outline-none">
                                <i class="ti ti-dots-vertical text-lg"></i>
                            </button>
                            
                            <!-- Dropdown Menu -->
                            <div x-show="open" x-transition.opacity style="display: none;" class="absolute right-10 top-2 mt-2 w-48 bg-white rounded-xl shadow-lg border border-gray-100 py-1 z-10 text-left">
                                <a href="{{ route('admin.teachers.show', $teacher->id) }}" class="block px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-blue-600 flex items-center gap-2">
                                    <i class="ti ti-eye"></i> View Profile
                                </a>
                                <button type="button" @click="$dispatch('open-edit-teacher-{{ $teacher->id }}'); open = false"
                                    class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-amber-600 flex items-center gap-2">
                                    <i class="ti ti-edit"></i> Edit Details
                                </button>
                                <div class="border-t border-gray-100 my-1"></div>

                                @unless($teacher->account_activated_at)
                                    {{-- Pending: re-issue the one-time activation code (e.g. if lost). --}}
                                    <form id="regen-code-{{ $teacher->id }}" action="{{ route('admin.users.activation-code', $teacher->id) }}" method="POST">
                                        @csrf
                                        <button type="submit"
                                            class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-indigo-600 flex items-center gap-2">
                                            <i class="ti ti-refresh"></i> Regenerate code
                                        </button>
                                    </form>
                                @endunless

                                <form id="reset-password-{{ $teacher->id }}" action="{{ route('admin.users.reset-password', $teacher->id) }}" method="POST">
                                    @csrf
                                    <button type="button"
                                        @click="$dispatch('open-confirm-modal', {
                                            formId: 'reset-password-{{ $teacher->id }}',
                                            title: 'Reset Account',
                                            message: 'Reset {{ addslashes($teacher->name) }}\'s account? They will be signed out and must re-activate with a new one-time code to set a new password.',
                                            confirmText: 'Yes, Reset & Re-issue Code',
                                            buttonClass: 'bg-amber-500 hover:bg-amber-600 shadow-amber-200',
                                            iconClass: 'ti-key text-amber-600',
                                            iconBgClass: 'bg-amber-50'
                                        })"
                                        class="w-full text-left px-4 py-2 text-sm text-gray-700 hover:bg-gray-50 hover:text-red-600 flex items-center gap-2">
                                        <i class="ti ti-key"></i> Reset Password
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="7" class="py-16 text-center">
                            <div class="flex flex-col items-center">
                                <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3">
                                    <i class="ti ti-user-off text-2xl"></i>
                                </div>
                                <h3 class="text-base font-medium text-gray-900 mb-1">No Teachers Found</h3>
                                <p class="text-sm text-gray-500 mb-4">Teacher accounts will appear here once they are registered in User Management.</p>
                                <a href="{{ route('admin.users.create') }}" class="px-4 py-2 bg-blue-600 text-white text-sm font-medium rounded-lg hover:bg-blue-700 transition">
                                    <i class="ti ti-plus mr-1"></i> Register a Teacher
                                </a>
                            </div>
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @if($teachers->hasPages())
        <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100">
            {{ $teachers->links() }}
        </div>
    @endif
</div>

{{-- ── Edit Details Modals ───────────────────────────────────── --}}
@foreach($teachers as $teacher)
<div x-data="{ showEditModal: {{ $errors->any() && old('edit_teacher_id') == $teacher->id ? 'true' : 'false' }} }"
     @open-edit-teacher-{{ $teacher->id }}.window="showEditModal = true">
    <div x-show="showEditModal" class="fixed inset-0 z-50 overflow-y-auto" style="display: none;" x-cloak>
        <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
            <div x-show="showEditModal" x-transition.opacity class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" aria-hidden="true" @click="showEditModal = false"></div>
            <span class="hidden sm:inline-block sm:align-middle sm:h-screen" aria-hidden="true">&#8203;</span>
            <div x-show="showEditModal" x-transition.scale.origin.bottom class="inline-block w-full max-w-lg p-6 my-8 overflow-hidden text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8">
                <div class="flex items-center justify-between mb-5">
                    <div>
                        <h3 class="text-xl font-bold text-gray-900">Edit Details</h3>
                        <p class="text-sm text-gray-500 mt-1">Update {{ $teacher->name }}'s account information.</p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-gray-400 hover:text-gray-600 transition bg-gray-50 hover:bg-gray-100 rounded-full p-2">
                        <i class="ti ti-x text-xl"></i>
                    </button>
                </div>

                <form action="{{ route('admin.teachers.update', $teacher->id) }}" method="POST">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="edit_teacher_id" value="{{ $teacher->id }}">

                    <div class="space-y-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Full Name <span class="text-red-500">*</span></label>
                            <input type="text" name="name" value="{{ old('edit_teacher_id') == $teacher->id ? old('name', $teacher->name) : $teacher->name }}" required
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_teacher_id') == $teacher->id) @error('name') border-red-500 @enderror @endif">
                            @if(old('edit_teacher_id') == $teacher->id) @error('name') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Employee ID (Optional)</label>
                            <input type="text" name="username" value="{{ old('edit_teacher_id') == $teacher->id ? old('username', $teacher->username) : $teacher->username }}"
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_teacher_id') == $teacher->id) @error('username') border-red-500 @enderror @endif">
                            @if(old('edit_teacher_id') == $teacher->id) @error('username') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Email Address</label>
                            <input type="email" name="email" value="{{ old('edit_teacher_id') == $teacher->id ? old('email', $teacher->email) : $teacher->email }}"
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_teacher_id') == $teacher->id) @error('email') border-red-500 @enderror @endif">
                            @if(old('edit_teacher_id') == $teacher->id) @error('email') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                        </div>

                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">New Password (Optional)</label>
                            <input type="password" name="password"
                                class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @if(old('edit_teacher_id') == $teacher->id) @error('password') border-red-500 @enderror @endif">
                            <p class="mt-1 text-[11px] text-gray-500">Leave blank to keep the current password. Minimum 8 characters.</p>
                            @if(old('edit_teacher_id') == $teacher->id) @error('password') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror @endif
                        </div>
                    </div>

                    <div class="mt-6 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                        <button type="button" @click="showEditModal = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                            Cancel
                        </button>
                        <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                            <i class="ti ti-device-floppy"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
@endforeach

@endsection

@push('scripts')
<script>
    let searchTimeout = null;
    const searchInput = document.getElementById('searchInput');
    const filterForm = document.getElementById('filterForm');

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

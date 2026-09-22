@extends('layouts.admin')

@section('title', 'Behavioral Reports')
@section('page-title', 'Behavioral Reports')
@section('page-sub', 'Monitor student behavioral incidents reported by teachers')

@section('content')

{{-- ── Summary Cards ─────────────────────────────────────────── --}}
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 mb-5">
    {{-- Total Reports --}}
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center justify-between gap-3 hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-gray-50 flex items-center justify-center shrink-0">
                <i class="ti ti-message-report text-gray-500 text-lg"></i>
            </div>
            <div>
                <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($totalReports) }}</div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Total Reports</div>
            </div>
        </div>
    </div>
    {{-- Pending Review --}}
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center justify-between gap-3 hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-blue-50 flex items-center justify-center shrink-0">
                <i class="ti ti-clock text-blue-600 text-lg"></i>
            </div>
            <div>
                <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($pendingCount) }}</div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Pending</div>
            </div>
        </div>
    </div>
    {{-- Reviewed --}}
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center justify-between gap-3 hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-amber-50 flex items-center justify-center shrink-0">
                <i class="ti ti-eye text-amber-600 text-lg"></i>
            </div>
            <div>
                <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($reviewedCount) }}</div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Reviewed</div>
            </div>
        </div>
    </div>
    {{-- Resolved --}}
    <div class="bg-white border border-gray-100 rounded-xl p-3 flex items-center justify-between gap-3 hover:shadow-md transition">
        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-lg bg-green-50 flex items-center justify-center shrink-0">
                <i class="ti ti-circle-check text-green-600 text-lg"></i>
            </div>
            <div>
                <div class="text-lg font-bold text-gray-900 leading-none">{{ number_format($resolvedCount) }}</div>
                <div class="text-[11px] font-medium text-gray-400 uppercase tracking-wide mt-1">Resolved</div>
            </div>
        </div>
    </div>
</div>


{{-- ── Filters ───────────────────────────────────────────────── --}}
<div class="mb-6">
    <form action="{{ route('admin.behavioral-reports.index') }}" method="GET" class="w-full" x-data id="filterForm">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-premium p-4 flex flex-col lg:flex-row gap-3 items-end">
            {{-- Search --}}
            <div class="flex-1 w-full relative">
                <label class="block text-xs font-medium text-gray-500 mb-1">Search Student</label>
                <div class="relative">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <i class="ti ti-search text-gray-400"></i>
                    </div>
                    <input type="text" id="searchInput" name="search" value="{{ request('search') }}" placeholder="Name or Student ID..."
                        class="block w-full pl-10 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition" autocomplete="off">
                </div>
            </div>
            
            {{-- Teacher Filter --}}
            <div class="w-full lg:w-40">
                <label class="block text-xs font-medium text-gray-500 mb-1">Reported By</label>
                <select name="reported_by_id" @change="$el.closest('form').submit()" class="block w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition cursor-pointer">
                    <option value="">All Teachers</option>
                    @if(isset($teachers))
                        @foreach($teachers as $teacher)
                            <option value="{{ $teacher->id }}" {{ request('reported_by_id') == $teacher->id ? 'selected' : '' }}>{{ $teacher->name }}</option>
                        @endforeach
                    @endif
                </select>
            </div>

            {{-- Severity Filter --}}
            <div class="w-full lg:w-32">
                <label class="block text-xs font-medium text-gray-500 mb-1">Severity</label>
                <select name="severity" @change="$el.closest('form').submit()" class="block w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition cursor-pointer">
                    <option value="">All Severity</option>
                    <option value="minor" {{ request('severity') == 'minor' ? 'selected' : '' }}>Minor</option>
                    <option value="moderate" {{ request('severity') == 'moderate' ? 'selected' : '' }}>Moderate</option>
                    <option value="severe" {{ request('severity') == 'severe' ? 'selected' : '' }}>Severe</option>
                </select>
            </div>

            {{-- Status Filter --}}
            <div class="w-full lg:w-32">
                <label class="block text-xs font-medium text-gray-500 mb-1">Status</label>
                <select name="status" @change="$el.closest('form').submit()" class="block w-full py-2 px-3 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm shadow-sm transition cursor-pointer">
                    <option value="">All Status</option>
                    <option value="pending" {{ request('status') == 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="reviewed" {{ request('status') == 'reviewed' ? 'selected' : '' }}>Reviewed</option>
                    <option value="resolved" {{ request('status') == 'resolved' ? 'selected' : '' }}>Resolved</option>
                </select>
            </div>

            {{-- Action Buttons --}}
            <div class="flex gap-2 w-full lg:w-auto mt-3 lg:mt-0">
                <a href="{{ route('admin.behavioral-reports.index') }}" class="px-4 py-2 bg-white border border-gray-300 text-gray-600 text-sm font-medium rounded-lg hover:bg-gray-50 transition flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-x"></i> Reset
                </a>
                <a href="{{ route('admin.behavioral-reports.export', request()->all()) }}" class="px-4 py-2 bg-green-600 text-white text-sm font-medium rounded-lg hover:bg-green-700 transition shadow-sm flex items-center justify-center gap-1.5 w-full lg:w-auto whitespace-nowrap">
                    <i class="ti ti-file-spreadsheet"></i> Export CSV
                </a>
            </div>
        </div>
    </form>
</div>

{{-- ── Reports Table ─────────────────────────────────────────── --}}
<div x-data="bulkActions()" class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left border-collapse">
            <thead>
                <tr class="bg-gray-50/50 border-b border-gray-100">
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest w-12 text-center">
                        <input type="checkbox" @click="toggleAll" x-ref="masterCheckbox" class="rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-200 transition cursor-pointer">
                    </th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Student</th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Incident Type</th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Severity</th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Status</th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Reported By</th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest">Date</th>
                    <th class="px-5 py-4 text-[10px] font-bold text-gray-400 uppercase tracking-widest text-center">Action</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100">
            @forelse($reports as $report)
                <tr class="hover:bg-gray-50/50 transition">
                    <td class="px-5 py-3 text-center">
                        <input type="checkbox" value="{{ $report->id }}" x-model="selectedIds" class="behavioral-checkbox rounded border-gray-300 text-blue-600 focus:ring focus:ring-blue-200 transition cursor-pointer">
                    </td>
                    <td class="px-5 py-3">
                        <div class="flex flex-col items-start gap-1">
                            <a href="{{ route('admin.behavioral-reports.show', $report->id) }}" class="font-medium text-gray-900 hover:text-blue-600 hover:underline transition">{{ $report->student->last_name }}, {{ $report->student->first_name }}</a>
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-bold bg-gray-100 text-gray-600 border border-gray-200 shrink-0">{{ $report->student->student_id_number }}</span>
                        </div>
                    </td>
                    <td class="px-5 py-3 text-gray-600">{{ $report->incident_type }}</td>
                    <td class="px-5 py-3 text-center">
                        @php
                            $severityClass = match($report->severity) {
                                'severe'   => 'bg-red-50 text-red-700',
                                'moderate' => 'bg-amber-50 text-amber-700',
                                'minor'    => 'bg-green-50 text-green-700',
                                'Critical' => 'bg-red-50 text-red-700',
                                'High'     => 'bg-red-50 text-red-700',
                                'Medium'   => 'bg-amber-50 text-amber-700',
                                'Low'      => 'bg-green-50 text-green-700',
                                default    => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $severityClass }}">
                            {{ ucfirst($report->severity) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-center">
                        @php
                            $statusClass = match($report->status) {
                                'pending'  => 'bg-blue-50 text-blue-700',
                                'reviewed' => 'bg-amber-50 text-amber-700',
                                'resolved' => 'bg-green-50 text-green-700',
                                default    => 'bg-gray-100 text-gray-500',
                            };
                        @endphp
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium {{ $statusClass }}">
                            {{ ucfirst($report->status) }}
                        </span>
                    </td>
                    <td class="px-5 py-3 text-gray-600 text-xs">{{ $report->reportedBy->name ?? '—' }}</td>
                    <td class="px-5 py-3 text-xs text-gray-500">{{ $report->incident_date->format('M d, Y') }}</td>
                    <td class="px-5 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <a href="{{ route('admin.behavioral-reports.show', $report->id) }}" class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-blue-50 text-blue-600 hover:bg-blue-100 transition" title="View Report">
                                <i class="ti ti-eye"></i>
                            </a>
                            @if($report->status === 'pending')
                                <a href="{{ route('admin.referrals.create', ['student_id' => $report->student_id, 'reason' => 'Escalated from Behavioral Report: ' . $report->incident_type]) }}" 
                                   class="inline-flex items-center justify-center w-7 h-7 rounded-md bg-red-50 text-red-600 hover:bg-red-100 transition" title="Issue Referral">
                                    <i class="ti ti-file-alert"></i>
                                </a>
                            @endif
                        </div>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" class="py-16 text-center">
                        <div class="flex flex-col items-center">
                            <div class="w-16 h-16 bg-gray-50 rounded-full flex items-center justify-center text-gray-300 mb-3">
                                <i class="ti ti-mood-check text-2xl"></i>
                            </div>
                            <h3 class="text-base font-medium text-gray-900 mb-1">No Behavioral Reports</h3>
                            <p class="text-sm text-gray-500">Behavioral reports submitted by teachers will appear here.</p>
                        </div>
                    </td>
                </tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if($reports->hasPages())
        <div class="px-5 py-4 bg-gray-50/50 border-t border-gray-100">
            {{ $reports->links() }}
        </div>
    @endif
    
    <!-- Floating Bulk Action Toolbar -->
    <div x-show="selectedIds.length > 0"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0 translate-y-8 scale-95"
         x-transition:enter-end="opacity-100 translate-y-0 scale-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100 translate-y-0 scale-100"
         x-transition:leave-end="opacity-0 translate-y-8 scale-95"
         class="fixed bottom-8 left-1/2 -translate-x-1/2 z-50 flex items-center gap-4 bg-white/80 backdrop-blur-xl border border-gray-200/60 shadow-2xl rounded-2xl px-6 py-4"
         style="display: none;">

        <div class="flex items-center gap-3 pr-4 border-r border-gray-200/60">
            <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-sm" x-text="selectedIds.length"></div>
            <span class="text-sm font-semibold text-gray-700">Selected</span>
        </div>

        <div class="flex items-center gap-2">
            <form action="{{ route('admin.behavioral-reports.bulkAction') }}" method="POST" class="inline-flex m-0">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <input type="hidden" name="action" value="mark_reviewed">
                <button type="submit" class="px-4 py-2 bg-amber-50 text-amber-700 hover:bg-amber-100 rounded-xl text-sm font-medium transition flex items-center gap-2">
                    <i class="ti ti-eye"></i> Mark Reviewed
                </button>
            </form>

            <form action="{{ route('admin.behavioral-reports.bulkAction') }}" method="POST" class="inline-flex m-0">
                @csrf
                <template x-for="id in selectedIds" :key="id">
                    <input type="hidden" name="ids[]" :value="id">
                </template>
                <input type="hidden" name="action" value="mark_resolved">
                <button type="submit" class="px-4 py-2 bg-green-50 text-green-700 hover:bg-green-100 rounded-xl text-sm font-medium transition flex items-center gap-2">
                    <i class="ti ti-circle-check"></i> Mark Resolved
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('alpine:init', () => {
        Alpine.data('bulkActions', () => ({
            selectedIds: [],
            
            toggleAll() {
                const checkboxes = document.querySelectorAll('.behavioral-checkbox');
                if (this.$refs.masterCheckbox.checked) {
                    this.selectedIds = Array.from(checkboxes).map(cb => cb.value);
                } else {
                    this.selectedIds = [];
                }
            }
        }));
    });

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
@endsection

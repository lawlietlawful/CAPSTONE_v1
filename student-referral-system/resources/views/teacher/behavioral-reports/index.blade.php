@extends('layouts.teacher')

@section('title', 'My Behavioral Reports')
@section('page-title', 'My Behavioral Reports')
@section('page-sub', 'Track and manage the behavioral incidents you have reported')

@section('content')

<div x-data="{ showModal: {{ $errors->any() ? 'true' : 'false' }} }">
    {{-- ── Header & Actions ────────────────────────────────────────── --}}
    <div class="mb-6 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
            <h2 class="text-lg font-semibold text-gray-800">Submitted Reports</h2>
            <p class="text-sm text-gray-500">View the status of the incidents you've reported to guidance.</p>
        </div>
        <div>
            <button @click="showModal = true" type="button" class="inline-flex items-center gap-2 px-5 py-2.5 bg-blue-600 text-white font-medium rounded-xl hover:bg-blue-700 transition shadow-sm hover:shadow-md">
                <i class="ti ti-plus"></i> File New Report
            </button>
        </div>
    </div>

    {{-- ── Data Table ────────────────────────────────────────────────── --}}
    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-left text-sm text-gray-600">
                <thead class="bg-gray-50/50 text-gray-500 text-xs uppercase tracking-wider border-b border-gray-100">
                    <tr>
                        <th scope="col" class="px-6 py-4 font-semibold">Report ID</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Student</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Incident Details</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Date Filed</th>
                        <th scope="col" class="px-6 py-4 font-semibold">Status</th>
                        <th scope="col" class="px-6 py-4 font-semibold text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100/75">
                    @forelse($reports as $report)
                        <tr class="hover:bg-blue-50/30 transition duration-150">
                            <td class="px-6 py-4 align-middle">
                                <span class="font-medium text-gray-900">#{{ $report->id }}</span>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ substr($report->student->first_name, 0, 1) }}{{ substr($report->student->last_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $report->student->last_name }}, {{ $report->student->first_name }}</div>
                                        <div class="text-xs text-gray-500">{{ $report->student->student_id_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <div class="font-medium text-gray-800">{{ $report->incident_type }}</div>
                                @php
                                    $sevBadge = match($report->severity) {
                                        'minor', 'Low'               => 'bg-green-50 text-green-700 border-green-200',
                                        'moderate', 'Medium'         => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'severe', 'High', 'Critical' => 'bg-red-50 text-red-700 border-red-200',
                                        default                      => 'bg-gray-100 text-gray-600 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2 py-0.5 mt-1 rounded text-[10px] font-medium border {{ $sevBadge }}">
                                    {{ ucfirst($report->severity) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <div class="text-gray-900">{{ $report->created_at->format('M j, Y') }}</div>
                                <div class="text-xs text-gray-500">{{ $report->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                @php
                                    $statusBadge = match($report->status) {
                                        'pending'  => 'bg-blue-50 text-blue-700 border-blue-200',
                                        'reviewed' => 'bg-amber-50 text-amber-700 border-amber-200',
                                        'resolved' => 'bg-green-50 text-green-700 border-green-200',
                                        default    => 'bg-gray-100 text-gray-600 border-gray-200',
                                    };
                                @endphp
                                <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border {{ $statusBadge }}">
                                    {{ ucfirst($report->status) }}
                                </span>
                            </td>
                            <td class="px-6 py-4 align-middle text-right">
                                <div class="flex justify-end gap-2">
                                    <a href="{{ route('teacher.behavioral-reports.show', $report->id) }}" class="p-2 text-blue-600 hover:bg-blue-50 rounded-lg transition" title="View Details">
                                        <i class="ti ti-eye text-lg"></i>
                                    </a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-6 py-12 text-center">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-16 w-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <i class="ti ti-inbox text-2xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No reports filed</h3>
                                    <p class="text-sm text-gray-500 mb-4">You haven't filed any behavioral reports yet.</p>
                                    <button type="button" @click="showModal = true" class="inline-flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 text-gray-700 text-sm font-medium rounded-lg hover:bg-gray-50 transition shadow-sm">
                                        <i class="ti ti-plus"></i> File a Report
                                    </button>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        
        @if($reports->hasPages())
        <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30">
            {{ $reports->links() }}
        </div>
        @endif
    </div>

    <!-- The Modal -->
    <div x-show="showModal" style="display: none;" class="relative z-50" aria-labelledby="modal-title" role="dialog" aria-modal="true">
        <!-- Background overlay -->
        <div x-show="showModal"
             x-transition:enter="ease-out duration-300"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-200"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 bg-gray-900/40 backdrop-blur-sm transition-opacity"></div>
      
        <div class="fixed inset-0 z-10 w-screen overflow-y-auto">
            <div class="flex min-h-full items-end justify-center p-4 text-center sm:items-center sm:p-0">
                <!-- Modal panel -->
                <div x-show="showModal"
                     @click.away="showModal = false"
                     x-transition:enter="ease-out duration-300"
                     x-transition:enter-start="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     x-transition:enter-end="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave="ease-in duration-200"
                     x-transition:leave-start="opacity-100 translate-y-0 sm:scale-100"
                     x-transition:leave-end="opacity-0 translate-y-4 sm:translate-y-0 sm:scale-95"
                     class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-premium transition-all sm:my-8 sm:w-full sm:max-w-3xl border border-gray-100">
                    
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <div class="h-8 w-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="ti ti-file-description text-lg"></i>
                            </div>
                            New Report Form
                        </h3>
                        <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition p-1">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>

                    <div class="p-6">
                        <form action="{{ route('teacher.behavioral-reports.store') }}" method="POST">
                            @csrf
                            
                            <div class="space-y-6">
                                <!-- Student Selection with Tom Select -->
                                <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100">
                                    <label for="student_id" class="block text-sm font-semibold text-gray-800 mb-2">Select Student <span class="text-red-500">*</span></label>
                                    <div wire:ignore>
                                        <select name="student_id" id="report_student_id" required class="w-full @error('student_id') border-red-500 @enderror">
                                            <option value="">Search by name or ID...</option>
                                            @foreach($students as $student)
                                                <option value="{{ $student->id }}" {{ old('student_id') == $student->id ? 'selected' : '' }}>
                                                    {{ $student->last_name }}, {{ $student->first_name }} ({{ $student->student_id_number }}) - {{ $student->course ?? $student->grade_level }} {{ $student->section }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                    @error('student_id')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                    <p class="mt-2 text-xs text-gray-500"><i class="ti ti-info-circle"></i> Type a name or ID to search instantly.</p>
                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Incident Type -->
                                    <div class="md:col-span-2">
                                        <label for="incident_type" class="block text-sm font-semibold text-gray-800 mb-1">Report Type <span class="text-red-500">*</span></label>
                                        <select name="incident_type" id="incident_type" required
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm @error('incident_type') border-red-500 @enderror">
                                            <option value="">Select type...</option>
                                            @foreach(\App\Models\BehavioralReport::INCIDENT_TYPES as $value => $label)
                                                <option value="{{ $value }}" {{ old('incident_type') === $value ? 'selected' : '' }}>{{ $label }}</option>
                                            @endforeach
                                        </select>
                                        @error('incident_type')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                </div>

                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                    <!-- Date -->
                                    <div>
                                        <label for="incident_date" class="block text-sm font-semibold text-gray-800 mb-1">Date of Incident / Grade <span class="text-red-500">*</span></label>
                                        <input type="date" name="incident_date" id="incident_date" value="{{ old('incident_date', date('Y-m-d')) }}" required
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm @error('incident_date') border-red-500 @enderror">
                                        @error('incident_date')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Location -->
                                    <div>
                                        <label for="location" class="block text-sm font-semibold text-gray-800 mb-1">Location / Subject (Optional)</label>
                                        <input type="text" name="location" id="location" value="{{ old('location') }}" placeholder="e.g., Room 101 or IT102"
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm @error('location') border-red-500 @enderror">
                                        @error('location')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>

                                <!-- Description -->
                                <div>
                                    <label for="description" class="block text-sm font-semibold text-gray-800 mb-1">Description <span class="text-red-500">*</span></label>
                                    <textarea name="description" id="description" rows="4" required placeholder="Provide details of the failing grade or incident..."
                                        class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm @error('description') border-red-500 @enderror">{{ old('description') }}</textarea>
                                    @error('description')
                                        <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                    @enderror
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-between pt-5 border-t border-gray-100">
                                <div class="flex items-center gap-2 text-sm text-gray-500">
                                    <i class="ti ti-brain text-blue-500"></i>
                                    <span>Severity will be assessed by AI</span>
                                </div>
                                <div class="flex items-center gap-3">
                                    <button type="button" @click="showModal = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition shadow-sm">
                                        Cancel
                                    </button>
                                    <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm hover:shadow-md flex items-center gap-2">
                                        <i class="ti ti-send text-lg"></i> Submit Report
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

@endsection

@section('scripts')
<script>
    document.addEventListener("DOMContentLoaded", function() {
        if(document.getElementById('report_student_id')){
            new TomSelect("#report_student_id",{
                create: false,
                sortField: {
                    field: "text",
                    direction: "asc"
                },
                maxOptions: null, // Don't limit to 50 when searching
                placeholder: "Type to search..."
            });
        }
    });
</script>
@endsection

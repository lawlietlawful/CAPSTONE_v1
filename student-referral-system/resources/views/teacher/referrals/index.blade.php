@extends('layouts.teacher')

@section('title', 'My Referrals')
@section('page-title', 'My Referrals')
@section('page-sub', 'Track the status of students you have referred to the guidance office')

@section('content')
<div x-data="{ showModal: {{ $errors->any() ? 'true' : 'false' }}, referralType: '{{ old('referral_type') }}' }">
    <div class="mb-6 flex flex-col sm:flex-row items-center justify-between gap-4">
        <div class="flex gap-4">
            <div class="bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm text-sm">
                <span class="text-gray-500">Total Filed:</span> 
                <span class="font-bold text-gray-900 ml-1">{{ $referrals->total() }}</span>
            </div>
            <div class="bg-white px-4 py-2 rounded-lg border border-gray-200 shadow-sm text-sm">
                <span class="text-gray-500">Pending Review:</span> 
                <span class="font-bold text-orange-600 ml-1">{{ $pendingCount }}</span>
            </div>
        </div>
        
        <button @click="showModal = true" type="button" class="px-5 py-2.5 bg-blue-600 text-white rounded-xl text-sm font-medium hover:bg-blue-700 transition flex items-center gap-2 shadow-sm hover:shadow-md">
            <i class="ti ti-plus"></i> File New Referral
        </button>
    </div>

    <div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm text-left text-gray-600">
                <thead class="text-xs text-gray-500 uppercase bg-gray-50/50 border-b border-gray-100 tracking-wider">
                    <tr>
                        <th class="px-6 py-4 font-semibold">Date Filed</th>
                        <th class="px-6 py-4 font-semibold">Student</th>
                        <th class="px-6 py-4 font-semibold">Reason Type</th>
                        <th class="px-6 py-4 font-semibold">Status</th>
                        <th class="px-6 py-4 font-semibold">AI Assessment & Seminars</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100/75">
                    @forelse($referrals as $referral)
                        <tr class="hover:bg-blue-50/30 transition duration-150">
                            <td class="px-6 py-4 whitespace-nowrap align-middle">
                                <span class="font-medium text-gray-900">{{ $referral->created_at->format('M d, Y') }}</span>
                                <div class="text-xs text-gray-500">{{ $referral->created_at->format('h:i A') }}</div>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <div class="flex items-center gap-3">
                                    <div class="h-8 w-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center font-bold text-xs shrink-0">
                                        {{ substr($referral->student->first_name, 0, 1) }}{{ substr($referral->student->last_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <div class="font-semibold text-gray-900">{{ $referral->student->first_name }} {{ $referral->student->last_name }}</div>
                                        <div class="text-xs text-gray-500">ID: {{ $referral->student->student_id_number }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                <span class="font-medium text-gray-800">{{ $referral->referral_type_label }}</span>
                                <div class="text-xs text-gray-500 line-clamp-1 max-w-xs truncate" title="{{ $referral->display_reason }}">{{ $referral->display_reason }}</div>
                            </td>
                            <td class="px-6 py-4 align-middle">
                                @if($referral->status === 'pending')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-orange-50 text-orange-700 border border-orange-100">
                                        <span class="w-1.5 h-1.5 rounded-full bg-orange-500 animate-pulse"></span> Pending Review
                                    </span>
                                @elseif($referral->status === 'in_progress')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                        <i class="ti ti-loader text-blue-500"></i> In Progress
                                    </span>
                                @elseif($referral->status === 'resolved')
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-green-50 text-green-700 border border-green-100">
                                        <i class="ti ti-check text-green-500"></i> Resolved
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-md text-xs font-medium bg-gray-100 text-gray-700 border border-gray-200">
                                        {{ ucfirst($referral->status) }}
                                    </span>
                                @endif
                            </td>
                            <td class="px-6 py-4 align-middle text-gray-600">
                                @if($referral->riskAssessment)
                                    <div class="mb-1">
                                        @if($referral->riskAssessment->risk_level == 'high')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-red-100 text-red-800 border border-red-200">High Risk ({{ $referral->riskAssessment->risk_score }}%)</span>
                                        @elseif($referral->riskAssessment->risk_level == 'moderate')
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-yellow-100 text-yellow-800 border border-yellow-200">Moderate Risk ({{ $referral->riskAssessment->risk_score }}%)</span>
                                        @else
                                            <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-green-100 text-green-800 border border-green-200">Low Risk ({{ $referral->riskAssessment->risk_score }}%)</span>
                                        @endif
                                    </div>
                                @else
                                    <span class="text-xs text-gray-400 italic">Not Assessed</span>
                                @endif
                                
                                @if($referral->student->seminars->count() > 0)
                                    <div class="text-xs text-blue-600 mt-1 flex items-center gap-1">
                                        <i class="ti ti-books"></i> Enrolled: {{ $referral->student->seminars->first()->title }}
                                    </div>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-12 text-center text-gray-500">
                                <div class="flex flex-col items-center justify-center">
                                    <div class="h-16 w-16 bg-gray-50 rounded-full flex items-center justify-center mb-4">
                                        <i class="ti ti-file-export text-2xl text-gray-400"></i>
                                    </div>
                                    <h3 class="text-sm font-medium text-gray-900 mb-1">No referrals filed</h3>
                                    <p class="text-sm">You haven't filed any referrals yet.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($referrals->hasPages())
            <div class="px-6 py-4 border-t border-gray-100 bg-gray-50/30">
                {{ $referrals->links() }}
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
                     class="relative transform overflow-hidden rounded-2xl bg-white text-left shadow-premium transition-all sm:my-8 sm:w-full sm:max-w-2xl border border-gray-100">
                    
                    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                        <h3 class="font-bold text-gray-800 text-lg flex items-center gap-2">
                            <div class="h-8 w-8 rounded-lg bg-blue-100 text-blue-600 flex items-center justify-center">
                                <i class="ti ti-file-export text-lg"></i>
                            </div>
                            File New Referral
                        </h3>
                        <button type="button" @click="showModal = false" class="text-gray-400 hover:text-gray-600 transition p-1">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>

                    <div class="p-6">
                        <form action="{{ route('teacher.referrals.store') }}" method="POST">
                            @csrf
                            
                            <div class="space-y-6">
                                <!-- Student Selection with Tom Select -->
                                <div class="bg-gray-50/50 p-5 rounded-xl border border-gray-100">
                                    <label for="student_id" class="block text-sm font-semibold text-gray-800 mb-2">Select Student <span class="text-red-500">*</span></label>
                                    <div wire:ignore>
                                        <select name="student_id" id="referral_student_id" required class="w-full @error('student_id') border-red-500 @enderror">
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

                                <div class="grid grid-cols-1 gap-6">
                                    <!-- Referral Type -->
                                    <div>
                                        <label class="block text-sm font-semibold text-gray-800 mb-2">Reason for Referral <span class="text-red-500">*</span></label>
                                        <div class="space-y-2">
                                            @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                                                <label class="flex items-center gap-2 text-sm text-gray-700">
                                                    <input type="radio" name="referral_type" value="{{ $type }}" x-model="referralType" required
                                                        class="border-gray-300 text-blue-600 focus:ring-blue-200">
                                                    {{ $type }}
                                                </label>
                                            @endforeach
                                        </div>
                                        <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                                            <input type="text" name="referral_type_other" value="{{ old('referral_type_other') }}" placeholder="Please specify"
                                                class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm">
                                        </div>
                                        @error('referral_type')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                        @error('referral_type_other')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>

                                    <!-- Concern Type -->
                                    <div>
                                        <label for="concern_type" class="block text-sm font-semibold text-gray-800 mb-1">Concern Type <span class="text-red-500">*</span></label>
                                        <select name="concern_type" id="concern_type" required
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm @error('concern_type') border-red-500 @enderror">
                                            <option value="">Select concern type...</option>
                                            <option value="academic" {{ old('concern_type') === 'academic' ? 'selected' : '' }}>Academic</option>
                                            <option value="behavioral" {{ old('concern_type') === 'behavioral' ? 'selected' : '' }}>Behavioral</option>
                                            <option value="emotional" {{ old('concern_type') === 'emotional' ? 'selected' : '' }}>Emotional</option>
                                            <option value="family" {{ old('concern_type') === 'family' ? 'selected' : '' }}>Family</option>
                                            <option value="peer_conflict" {{ old('concern_type') === 'peer_conflict' ? 'selected' : '' }}>Peer Conflict</option>
                                            <option value="attendance" {{ old('concern_type') === 'attendance' ? 'selected' : '' }}>Attendance Concern</option>
                                            <option value="other" {{ old('concern_type') === 'other' ? 'selected' : '' }}>Other</option>
                                        </select>
                                        @error('concern_type')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                    
                                    <!-- Context/Reason -->
                                    <div>
                                        <label for="reason" class="block text-sm font-semibold text-gray-800 mb-1">Detailed Context <span class="text-red-500">*</span></label>
                                        <textarea name="reason" id="reason" rows="4" required placeholder="Please describe the situation, frequency of issue, and any prior interventions..."
                                            class="bg-gray-50 border border-gray-300 text-gray-900 text-sm rounded-xl focus:ring-blue-500 focus:border-blue-500 block w-full p-2.5 transition-colors shadow-sm @error('reason') border-red-500 @enderror">{{ old('reason') }}</textarea>
                                        @error('reason')
                                            <p class="mt-1 text-sm text-red-500">{{ $message }}</p>
                                        @enderror
                                    </div>
                                </div>
                            </div>

                            <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                                <button type="button" @click="showModal = false" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-xl hover:bg-gray-50 transition shadow-sm">
                                    Cancel
                                </button>
                                <button type="submit" class="px-6 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-xl hover:bg-blue-700 transition shadow-sm hover:shadow-md flex items-center gap-2">
                                    <i class="ti ti-send text-lg"></i> Submit Referral
                                </button>
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
        if(document.getElementById('referral_student_id')){
            new TomSelect("#referral_student_id",{
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

@extends('layouts.counselor')

@section('title', 'Intervention Details')
@section('page-title', 'Intervention Details')
@section('page-sub', 'Review notes and update the outcome of this session')

@section('content')

<div x-data="{ activeModal: {!! old('edit_intervention_marker') ? "'edit'" : 'null' !!} }">

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('counselor.interventions.index') }}" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Logs
    </a>
    <div class="flex items-center gap-3">
        @if($intervention->counselor_id !== auth()->id())
            <span class="text-xs text-gray-400 italic flex items-center gap-1.5">
                <i class="ti ti-lock"></i> Logged by {{ $intervention->counselor->name ?? 'another counselor' }} — only they can edit or delete this record.
            </span>
        @endif
        <div class="flex gap-2">
            <a href="{{ route('counselor.interventions.print', $intervention->id) }}" target="_blank" class="px-4 py-2 bg-white border border-gray-300 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-50 transition shadow-sm flex items-center gap-2">
                <i class="ti ti-printer"></i> Print
            </a>
            @if($intervention->counselor_id === auth()->id())
                <button type="button" @click="activeModal = 'edit'" class="px-4 py-2 bg-gray-100 text-gray-700 rounded-lg text-sm font-medium hover:bg-gray-200 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-edit"></i> Edit Details
                </button>
                <form id="delete-intervention-{{ $intervention->id }}" action="{{ route('counselor.interventions.destroy', $intervention->id) }}" method="POST">
                    @csrf @method('DELETE')
                    <button type="button" @click="$dispatch('open-confirm-modal', {
                            formId: 'delete-intervention-{{ $intervention->id }}',
                            title: 'Delete Intervention Log',
                            message: 'Are you sure you want to delete this intervention log? This action cannot be undone.',
                            confirmText: 'Yes, Delete Log'
                        })" class="bg-red-50 text-red-600 hover:bg-red-100 px-4 py-2 rounded-lg text-sm font-medium transition shadow-sm flex items-center gap-2">
                        <i class="ti ti-trash"></i> Delete Log
                    </button>
                </form>
            @endif
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    {{-- Left: Session Details --}}
    <div class="lg:col-span-2 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
                <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
                    <i class="ti ti-notes text-blue-600"></i> Session Record
                </h3>
                <div class="flex flex-col items-end gap-2">
                    <div class="flex items-center gap-2">
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border bg-blue-50 text-blue-700 border-blue-200">
                            {{ $intervention->intervention_type }}
                        </span>
                        @php
                            $outcomeClass = match($intervention->outcome) {
                                'improving' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
                                'worsening' => 'bg-red-50 text-red-700 border-red-200',
                                'resolved'  => 'bg-indigo-50 text-indigo-700 border-indigo-200',
                                'no_change' => 'bg-amber-50 text-amber-700 border-amber-200',
                                default     => 'bg-gray-100 text-gray-500 border-gray-200',
                            };
                            $outcomeLabel = $intervention->outcome ? ucfirst(str_replace('_', ' ', $intervention->outcome)) : 'Not Evaluated';
                        @endphp
                        <span class="inline-flex items-center px-2.5 py-1 rounded-md text-xs font-medium border {{ $outcomeClass }}">
                            {{ $outcomeLabel }}
                        </span>
                    </div>
                    <span class="text-xs text-gray-500">{{ \Carbon\Carbon::parse($intervention->intervention_date)->format('l, F d, Y') }}</span>
                </div>
            </div>

            <div class="p-6 space-y-5">
                @if($intervention->is_follow_up_overdue)
                    <div class="flex items-center gap-2 text-sm text-red-700 bg-red-50 border border-red-200 rounded-lg px-4 py-3">
                        <i class="ti ti-alert-triangle text-lg"></i>
                        <span>Follow-up was due <span class="font-semibold">{{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('F d, Y') }}</span> and is now overdue.</span>
                    </div>
                @endif

                <div>
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Session Notes & Description</span>
                    <p class="text-sm text-gray-700 leading-relaxed bg-gray-50 rounded-lg p-4 border border-gray-100 whitespace-pre-wrap">{{ $intervention->description }}</p>
                </div>

                <div class="pt-4 border-t border-gray-100">
                    <span class="block text-xs text-gray-400 uppercase tracking-wider mb-0.5">Follow-up Date</span>
                    @if($intervention->follow_up_date)
                        @if($intervention->is_follow_up_overdue)
                            <span class="font-medium text-red-600">{{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('F d, Y') }}</span>
                        @else
                            <span class="font-medium text-gray-900">{{ \Carbon\Carbon::parse($intervention->follow_up_date)->format('F d, Y') }}</span>
                        @endif
                    @else
                        <span class="text-gray-400 italic">No follow-up scheduled</span>
                    @endif
                </div>

                @if($intervention->follow_up_notes)
                    <div class="pt-4 border-t border-gray-100">
                        <span class="block text-xs text-gray-400 uppercase tracking-wider mb-1.5">Follow-up Requirements / Goals</span>
                        <p class="text-sm text-amber-900 leading-relaxed bg-amber-50 rounded-lg p-4 border border-amber-100 whitespace-pre-wrap">{{ $intervention->follow_up_notes }}</p>
                    </div>
                @endif
            </div>
        </div>

        @if($otherInterventions->isNotEmpty())
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-history text-indigo-500"></i> This Student's Other Interventions
                    </h3>
                    <p class="text-xs text-gray-400 mt-0.5">Across all of {{ $intervention->referral->student->first_name ?? 'this student' }}'s referrals, not just this one.</p>
                </div>
                <div class="divide-y divide-gray-100">
                    @foreach($otherInterventions as $other)
                        <a href="{{ route('counselor.interventions.show', $other->id) }}" class="flex items-center justify-between gap-4 px-6 py-3 hover:bg-gray-50/50 transition">
                            <div class="min-w-0">
                                <div class="flex items-center gap-2">
                                    <span class="font-medium text-gray-900 text-sm">{{ \Carbon\Carbon::parse($other->intervention_date)->format('M d, Y') }}</span>
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[11px] font-medium bg-blue-50 text-blue-700 border border-blue-100">
                                        {{ $other->intervention_type }}
                                    </span>
                                </div>
                                <p class="text-xs text-gray-500 mt-0.5 truncate">Ref #{{ str_pad($other->referral_id, 4, '0', STR_PAD_LEFT) }} — logged by {{ $other->counselor->name ?? 'a counselor' }}</p>
                            </div>
                            @if($other->outcome === 'improving')
                                <span class="text-xs text-emerald-600 font-medium whitespace-nowrap flex items-center gap-1"><i class="ti ti-trending-up"></i> Improving</span>
                            @elseif($other->outcome === 'worsening')
                                <span class="text-xs text-red-600 font-medium whitespace-nowrap flex items-center gap-1"><i class="ti ti-trending-down"></i> Worsening</span>
                            @elseif($other->outcome === 'resolved')
                                <span class="text-xs text-indigo-600 font-medium whitespace-nowrap flex items-center gap-1"><i class="ti ti-discount-check-filled"></i> Resolved</span>
                            @elseif($other->outcome === 'no_change')
                                <span class="text-xs text-amber-600 font-medium whitespace-nowrap flex items-center gap-1"><i class="ti ti-minus"></i> No Change</span>
                            @else
                                <span class="text-xs text-gray-400 italic whitespace-nowrap">Not evaluated</span>
                            @endif
                        </a>
                    @endforeach
                </div>
            </div>
        @endif
    </div>

    {{-- Right: Student & Referral Context --}}
    <div class="lg:col-span-1 space-y-6">
        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-user text-blue-500"></i> Student Profile
                </h3>
            </div>
            <div class="p-5 text-center">
                <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-blue-100 to-indigo-50 text-blue-700 flex items-center justify-center text-xl font-bold mx-auto mb-3 ring-4 ring-white shadow-md">
                    {{ strtoupper(substr($intervention->referral->student->first_name ?? 'X', 0, 1)) }}{{ strtoupper(substr($intervention->referral->student->last_name ?? '', 0, 1)) }}
                </div>
                @if($intervention->referral->student)
                    <a href="{{ route('admin.students.show', $intervention->referral->student->id) }}" class="font-bold text-gray-900 hover:text-blue-600 hover:underline transition">
                        {{ $intervention->referral->student->first_name }} {{ $intervention->referral->student->last_name }}
                    </a>
                @else
                    <h4 class="font-bold text-gray-900">Unknown Student</h4>
                @endif
                <p class="text-xs text-gray-500 mt-1">ID: {{ $intervention->referral->student->student_id_number ?? 'N/A' }}</p>
                <div class="mt-4 pt-4 border-t border-gray-100 flex justify-center gap-4 text-sm">
                    <div class="text-center min-w-0">
                        <div class="text-xs text-gray-400">Course</div>
                        <div class="font-medium text-gray-900">{{ $intervention->referral->student->course ?? 'N/A' }}</div>
                    </div>
                    <div class="text-center shrink-0">
                        <div class="text-xs text-gray-400">Year</div>
                        <div class="font-medium text-gray-900 whitespace-nowrap">{{ $intervention->referral->student->grade_level ?? 'N/A' }}</div>
                    </div>
                </div>
            </div>
        </div>

        @if($riskTrend)
            <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                    <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                        <i class="ti ti-chart-line text-purple-500"></i> Risk Trend
                    </h3>
                </div>
                <div class="p-5">
                    @if($riskTrend['status'] === 'no_new_assessment')
                        <div class="text-sm text-gray-700">
                            <span class="font-semibold">{{ $riskTrend['referral_score'] }}%</span> risk score at the time of this referral.
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">No newer AI assessment yet — check back after the student's next referral or scheduled reassessment.</p>
                    @else
                        @php
                            $trendClass = match($riskTrend['status']) {
                                'improved' => 'text-emerald-600',
                                'worsened' => 'text-red-600',
                                default    => 'text-gray-600',
                            };
                            $trendIcon = match($riskTrend['status']) {
                                'improved' => 'ti-trending-down',
                                'worsened' => 'ti-trending-up',
                                default    => 'ti-minus',
                            };
                            $trendLabel = match($riskTrend['status']) {
                                'improved' => 'Risk Improved',
                                'worsened' => 'Risk Worsened',
                                default    => 'Risk Unchanged',
                            };
                        @endphp
                        <div class="flex items-center gap-2 font-semibold {{ $trendClass }}">
                            <i class="ti {{ $trendIcon }} text-lg"></i> {{ $trendLabel }}
                        </div>
                        <div class="text-sm text-gray-700 mt-2 flex items-center gap-2">
                            <span>{{ $riskTrend['referral_score'] }}%</span>
                            <i class="ti ti-arrow-right text-gray-400 text-xs"></i>
                            <span class="font-semibold">{{ $riskTrend['latest_score'] }}%</span>
                        </div>
                        <p class="text-xs text-gray-400 mt-1.5">Latest assessment: {{ \Carbon\Carbon::parse($riskTrend['latest_assessed_at'])->format('M d, Y') }}</p>
                    @endif
                </div>
            </div>
        @endif

        <div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
            <div class="px-5 py-4 border-b border-gray-100 bg-gray-50/50">
                <h3 class="font-semibold text-gray-800 flex items-center gap-2">
                    <i class="ti ti-file-text text-amber-500"></i> Linked Referral
                </h3>
            </div>
            <div class="p-5">
                <div class="text-xs text-gray-500 mb-1">Referral #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</div>
                <div class="font-medium text-gray-900 text-sm mb-2">{{ $intervention->referral->referral_type_label }}</div>
                <div class="text-xs text-gray-600 line-clamp-3 italic">"{{ $intervention->referral->display_reason }}"</div>
                
                <div class="mt-4 pt-4 border-t border-gray-100 text-center">
                    <a href="{{ route('counselor.referrals.show', $intervention->referral_id) }}" class="text-xs font-medium text-blue-600 hover:text-blue-800 transition">View Full Referral →</a>
                </div>
            </div>
        </div>

        @if($intervention->counselor_id === auth()->id())
            <div x-data="{
                    confirmIfResolving() {
                        const form = document.getElementById('quick-update-form');
                        if (form.outcome.value === 'resolved' && form.dataset.originalOutcome !== 'resolved') {
                            this.$dispatch('open-confirm-modal', {
                                formId: 'quick-update-form',
                                title: 'Mark as Resolved?',
                                message: 'This will mark the outcome as Resolved and close the linked referral. Are you sure?',
                                confirmText: 'Yes, Resolve',
                                buttonClass: 'bg-blue-600 hover:bg-blue-700 shadow-blue-200',
                                iconClass: 'ti-circle-check text-blue-600',
                                iconBgClass: 'bg-blue-50',
                            });
                        } else {
                            form.submit();
                        }
                    }
                }" class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden">
                <div class="bg-gray-50/50 px-5 py-4 border-b border-gray-100 flex items-center gap-2">
                    <i class="ti ti-edit text-amber-600"></i>
                    <h4 class="font-semibold text-gray-800">Update Session</h4>
                </div>
                <div class="p-5">
                    <form id="quick-update-form" data-original-outcome="{{ $intervention->outcome }}" action="{{ route('counselor.interventions.quickUpdate', $intervention->id) }}" method="POST">
                        @csrf
                        @method('PATCH')
                        <div class="space-y-4">
                            <div>
                                <label for="quick_outcome" class="block text-sm font-medium text-gray-700 mb-1">Outcome</label>
                                <select name="outcome" id="quick_outcome"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                    <option value="" {{ $intervention->outcome ? '' : 'selected' }}>-- Not yet evaluated --</option>
                                    <option value="improving" {{ $intervention->outcome == 'improving' ? 'selected' : '' }}>Improving</option>
                                    <option value="no_change" {{ $intervention->outcome == 'no_change' ? 'selected' : '' }}>No Change</option>
                                    <option value="worsening" {{ $intervention->outcome == 'worsening' ? 'selected' : '' }}>Worsening</option>
                                    <option value="resolved" {{ $intervention->outcome == 'resolved' ? 'selected' : '' }}>Resolved (Closes Referral)</option>
                                </select>
                            </div>
                            <div>
                                <label for="quick_follow_up_date" class="block text-sm font-medium text-gray-700 mb-1">Follow-up Date</label>
                                <input type="date" name="follow_up_date" id="quick_follow_up_date"
                                    value="{{ $intervention->follow_up_date?->format('Y-m-d') }}"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                            </div>
                            <button type="button" @click="confirmIfResolving()" class="w-full px-4 py-2 bg-blue-600 text-white font-medium rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center justify-center gap-2">
                                <i class="ti ti-check"></i> Update Session
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>
</div>

@if($intervention->counselor_id === auth()->id())
    {{-- Edit Details Modal --}}
    <div x-cloak x-show="activeModal === 'edit'" x-data="{
            confirmIfResolving() {
                const form = document.getElementById('edit-intervention-modal-form');
                if (form.outcome.value === 'resolved' && form.dataset.originalOutcome !== 'resolved') {
                    this.$dispatch('open-confirm-modal', {
                        formId: 'edit-intervention-modal-form',
                        title: 'Mark as Resolved?',
                        message: 'This will mark the outcome as Resolved and close the linked referral. Are you sure?',
                        confirmText: 'Yes, Resolve',
                        buttonClass: 'bg-blue-600 hover:bg-blue-700 shadow-blue-200',
                        iconClass: 'ti-circle-check text-blue-600',
                        iconBgClass: 'bg-blue-50',
                    });
                } else {
                    form.submit();
                }
            }
        }">
        <div class="fixed inset-0 z-[100] overflow-y-auto">
            <div class="flex items-center justify-center min-h-screen px-4 pt-4 pb-20 text-center sm:block sm:p-0">
                <div class="fixed inset-0 transition-opacity bg-gray-900/60 backdrop-blur-sm" @click="activeModal = null"></div>
                <span class="hidden sm:inline-block sm:align-middle sm:h-screen">&#8203;</span>
                <div class="inline-block w-full max-w-2xl p-6 text-left align-middle transition-all transform bg-white shadow-premium rounded-2xl sm:p-8 relative z-[101]">
                    <div class="flex justify-between items-center mb-5 border-b border-gray-100 pb-4">
                        <h3 class="text-xl font-semibold text-gray-800 flex items-center gap-2">
                            <i class="ti ti-edit text-blue-500"></i> Edit Intervention Record
                        </h3>
                        <button type="button" @click="activeModal = null" class="text-gray-400 hover:text-gray-600 transition">
                            <i class="ti ti-x text-xl"></i>
                        </button>
                    </div>
                    <form id="edit-intervention-modal-form" data-original-outcome="{{ $intervention->outcome }}" action="{{ route('counselor.interventions.update', $intervention->id) }}" method="POST">
                        @csrf
                        @method('PUT')
                        <input type="hidden" name="edit_intervention_marker" value="1">
                        <div class="space-y-5">
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Intervention Type <span class="text-red-500">*</span></label>
                                    <select name="intervention_type" required class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('intervention_type') border-red-500 @enderror">
                                        @foreach($interventionTypes as $type)
                                            <option value="{{ $type }}" {{ old('intervention_type', $intervention->intervention_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                                        @endforeach
                                    </select>
                                    @error('intervention_type') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Date of Intervention <span class="text-red-500">*</span></label>
                                    <input type="date" name="intervention_date" required
                                        value="{{ old('intervention_date', $intervention->intervention_date->format('Y-m-d')) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('intervention_date') border-red-500 @enderror">
                                    @error('intervention_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Session Notes / Description <span class="text-red-500">*</span></label>
                                <textarea name="description" rows="3" required
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('description') border-red-500 @enderror">{{ old('description', $intervention->description) }}</textarea>
                                @error('description') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                            </div>
                            <div class="grid grid-cols-2 gap-5">
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Current Outcome</label>
                                    <select name="outcome" class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                                        <option value="" {{ old('outcome', $intervention->outcome) ? '' : 'selected' }}>-- Not yet evaluated --</option>
                                        <option value="improving" {{ old('outcome', $intervention->outcome) == 'improving' ? 'selected' : '' }}>Improving</option>
                                        <option value="no_change" {{ old('outcome', $intervention->outcome) == 'no_change' ? 'selected' : '' }}>No Change</option>
                                        <option value="worsening" {{ old('outcome', $intervention->outcome) == 'worsening' ? 'selected' : '' }}>Worsening</option>
                                        <option value="resolved" {{ old('outcome', $intervention->outcome) == 'resolved' ? 'selected' : '' }}>Resolved (Closes Referral)</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Scheduled Follow-up Date</label>
                                    <input type="date" name="follow_up_date"
                                        value="{{ old('follow_up_date', $intervention->follow_up_date?->format('Y-m-d')) }}"
                                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm @error('follow_up_date') border-red-500 @enderror">
                                    @error('follow_up_date') <p class="mt-1 text-xs text-red-500">{{ $message }}</p> @enderror
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1 text-left">Follow-up Requirements / Goals</label>
                                <textarea name="follow_up_notes" rows="2"
                                    class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ old('follow_up_notes', $intervention->follow_up_notes) }}</textarea>
                            </div>
                        </div>
                        <div class="mt-8 flex justify-end gap-3 pt-5 border-t border-gray-100">
                            <button type="button" @click="activeModal = null" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">Cancel</button>
                            <button type="button" @click="confirmIfResolving()" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2"><i class="ti ti-device-floppy"></i> Save Changes</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
@endif

</div> {{-- Close Alpine Wrapper --}}

@endsection

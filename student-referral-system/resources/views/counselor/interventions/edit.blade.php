@extends('layouts.counselor')

@section('title', 'Edit Intervention')
@section('page-title', 'Edit Intervention')
@section('page-sub', 'Update session notes, outcomes, or follow-up details')

@section('content')

<div class="mb-6 flex items-center justify-between">
    <a href="{{ route('counselor.interventions.show', $intervention->id) }}" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition flex items-center gap-1">
        <i class="ti ti-arrow-left"></i> Back to Details
    </a>
</div>

<div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden max-w-3xl">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50">
        <h3 class="font-semibold text-gray-800 flex items-center gap-2">
            <i class="ti ti-edit text-blue-500"></i> Edit Intervention Record
        </h3>
    </div>
    
    <div class="p-6">
        <div class="mb-6 p-4 bg-gray-50 rounded-lg border border-gray-100">
            <div class="text-xs text-gray-500">Student</div>
            <div class="font-semibold text-gray-900">{{ $intervention->referral->student->first_name ?? 'Unknown' }} {{ $intervention->referral->student->last_name ?? '' }}</div>
            <div class="text-xs text-gray-500 mt-1">Referral #{{ str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT) }}</div>
        </div>

        <form action="{{ route('counselor.interventions.update', $intervention->id) }}" method="POST" class="space-y-6">
            @csrf @method('PUT')

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Intervention Type --}}
                <div>
                    <label for="intervention_type" class="block text-sm font-medium text-gray-700 mb-1">Intervention Type <span class="text-red-500">*</span></label>
                    <select name="intervention_type" id="intervention_type" required
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                        @foreach($interventionTypes as $type)
                            <option value="{{ $type }}" {{ old('intervention_type', $intervention->intervention_type) == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('intervention_type') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Intervention Date --}}
                <div>
                    <label for="intervention_date" class="block text-sm font-medium text-gray-700 mb-1">Date of Intervention <span class="text-red-500">*</span></label>
                    <input type="date" name="intervention_date" id="intervention_date" required
                           value="{{ old('intervention_date', \Carbon\Carbon::parse($intervention->intervention_date)->format('Y-m-d')) }}"
                           class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                    @error('intervention_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Description / Notes --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Session Notes / Description <span class="text-red-500">*</span></label>
                <textarea name="description" id="description" rows="4" required 
                          class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">{{ old('description', $intervention->description) }}</textarea>
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <hr class="border-gray-100">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Outcome --}}
                <div>
                    <label for="outcome" class="block text-sm font-medium text-gray-700 mb-1">Current Outcome</label>
                    <select name="outcome" id="outcome"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                        <option value="">-- Not yet evaluated --</option>
                        <option value="improving" {{ old('outcome', $intervention->outcome) == 'improving' ? 'selected' : '' }}>Improving</option>
                        <option value="no_change" {{ old('outcome', $intervention->outcome) == 'no_change' ? 'selected' : '' }}>No Change</option>
                        <option value="worsening" {{ old('outcome', $intervention->outcome) == 'worsening' ? 'selected' : '' }}>Worsening</option>
                        <option value="resolved"  {{ old('outcome', $intervention->outcome) == 'resolved' ? 'selected' : '' }}>Resolved (Closes Referral)</option>
                    </select>
                    @error('outcome') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Follow-up Date --}}
                <div>
                    <label for="follow_up_date" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Follow-up Date (Optional)</label>
                    <input type="date" name="follow_up_date" id="follow_up_date"
                           value="{{ old('follow_up_date', $intervention->follow_up_date ? \Carbon\Carbon::parse($intervention->follow_up_date)->format('Y-m-d') : '') }}"
                           class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                    @error('follow_up_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="follow_up_notes" class="block text-sm font-medium text-gray-700 mb-1">Follow-up Requirements / Goals</label>
                <textarea name="follow_up_notes" id="follow_up_notes" rows="2" 
                          class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">{{ old('follow_up_notes', $intervention->follow_up_notes) }}</textarea>
                @error('follow_up_notes') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-device-floppy"></i> Update Details
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

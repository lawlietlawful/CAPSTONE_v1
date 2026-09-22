@extends('layouts.counselor')

@section('title', 'Log Intervention')
@section('page-title', 'Log New Intervention')
@section('page-sub', 'Record details of a counseling session or disciplinary action')

@section('content')

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium overflow-hidden max-w-3xl">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
            <i class="ti ti-heart-handshake text-blue-500"></i> Intervention Details
        </h3>
        <a href="{{ route('counselor.interventions.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-arrow-left"></i> Back
        </a>
    </div>
    
    <div class="p-6">
        <form action="{{ route('counselor.interventions.store') }}" method="POST" class="space-y-6">
            @csrf
            
            {{-- Target Referral --}}
            <div>
                <label for="referral_id" class="block text-sm font-medium text-gray-700 mb-1">Target Student / Referral <span class="text-red-500">*</span></label>
                <select name="referral_id" id="referral_id" required
                        class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                    <option value="" disabled selected>Select a pending or active referral...</option>
                    @foreach($referrals as $ref)
                        <option value="{{ $ref->id }}" {{ old('referral_id') == $ref->id ? 'selected' : '' }}>
                            {{ $ref->student->first_name }} {{ $ref->student->last_name }} (Ref #{{ str_pad($ref->id, 4, '0', STR_PAD_LEFT) }} - {{ $ref->referral_type_label }})
                        </option>
                    @endforeach
                </select>
                @error('referral_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Intervention Type --}}
                <div>
                    <label for="intervention_type" class="block text-sm font-medium text-gray-700 mb-1">Intervention Type <span class="text-red-500">*</span></label>
                    <select name="intervention_type" id="intervention_type" required
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                        <option value="" disabled selected>Select type...</option>
                        @foreach($interventionTypes as $type)
                            <option value="{{ $type }}" {{ old('intervention_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('intervention_type') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Intervention Date --}}
                <div>
                    <label for="intervention_date" class="block text-sm font-medium text-gray-700 mb-1">Date of Intervention <span class="text-red-500">*</span></label>
                    <input type="date" name="intervention_date" id="intervention_date" required
                           value="{{ old('intervention_date', date('Y-m-d')) }}"
                           class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                    @error('intervention_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            {{-- Description / Notes --}}
            <div>
                <label for="description" class="block text-sm font-medium text-gray-700 mb-1">Session Notes / Description <span class="text-red-500">*</span></label>
                <textarea name="description" id="description" rows="4" required placeholder="Describe what was discussed or action taken..."
                          class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ old('description') }}</textarea>
                @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <hr class="border-gray-100">

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                {{-- Outcome --}}
                <div>
                    <label for="outcome" class="block text-sm font-medium text-gray-700 mb-1">Current Outcome</label>
                    <select name="outcome" id="outcome"
                            class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                        <option value="">-- Not yet evaluated --</option>
                        <option value="improving" {{ old('outcome') == 'improving' ? 'selected' : '' }}>Improving</option>
                        <option value="no_change" {{ old('outcome') == 'no_change' ? 'selected' : '' }}>No Change</option>
                        <option value="worsening" {{ old('outcome') == 'worsening' ? 'selected' : '' }}>Worsening</option>
                        <option value="resolved"  {{ old('outcome') == 'resolved' ? 'selected' : '' }}>Resolved (Closes Referral)</option>
                    </select>
                    @error('outcome') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>

                {{-- Follow-up Date --}}
                <div>
                    <label for="follow_up_date" class="block text-sm font-medium text-gray-700 mb-1">Scheduled Follow-up Date (Optional)</label>
                    <input type="date" name="follow_up_date" id="follow_up_date"
                           value="{{ old('follow_up_date') }}"
                           class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">
                    @error('follow_up_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="follow_up_notes" class="block text-sm font-medium text-gray-700 mb-1">Follow-up Requirements / Goals</label>
                <textarea name="follow_up_notes" id="follow_up_notes" rows="2" placeholder="Goals set for the student before next session..."
                          class="w-full rounded-xl border border-gray-300 bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm">{{ old('follow_up_notes') }}</textarea>
                @error('follow_up_notes') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="px-5 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-device-floppy"></i> Save Intervention
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

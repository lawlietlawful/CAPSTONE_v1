@extends('layouts.admin')

@section('title', 'Create Referral')
@section('page-title', 'Submit New Referral')
@section('page-sub', 'Refer a student to the Guidance Office for intervention')

@section('content')

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium max-w-4xl">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between">
        <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
            <i class="ti ti-file-plus text-blue-600"></i> Referral Details
        </h3>
        <a href="{{ route('admin.referrals.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-arrow-left"></i> Back
        </a>
    </div>

    <div class="p-6">
        <form action="{{ route('admin.referrals.store') }}" method="POST"
              x-data="{ referralType: '{{ old('referral_type') ?: (isset($prefillReason) && str_contains(strtolower($prefillReason), 'absence') ? 'Absences' : '') }}' }">
            @csrf

            <div class="space-y-6">
                <!-- Student Selection -->
                <div>
                    <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Student <span class="text-red-500">*</span></label>
                    <select name="student_id" id="student_id" required
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('student_id') border-red-500 @enderror">
                        <option value="">Select a student...</option>
                        @foreach($students as $student)
                            <option value="{{ $student->id }}" {{ (isset($prefillStudent) && $prefillStudent == $student->id) || old('student_id') == $student->id ? 'selected' : '' }}>
                                {{ $student->last_name }}, {{ $student->first_name }} ({{ $student->student_id_number }}) — {{ $student->course ?? $student->grade_level }}
                            </option>
                        @endforeach
                    </select>
                    @error('student_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Type & Priority -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <label for="referral_type" class="block text-sm font-medium text-gray-700 mb-1">Referral Type <span class="text-red-500">*</span></label>
                        <select name="referral_type" id="referral_type" required x-model="referralType"
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('referral_type') border-red-500 @enderror">
                            <option value="">Select type...</option>
                            @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                                <option value="{{ $type }}">{{ $type }}</option>
                            @endforeach
                        </select>
                        @error('referral_type') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                            <input type="text" name="referral_type_other" value="{{ old('referral_type_other') }}" placeholder="Please specify"
                                class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                            @error('referral_type_other') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                        </div>
                    </div>
                    <div>
                        <label for="priority" class="block text-sm font-medium text-gray-700 mb-1">Priority Level <span class="text-red-500">*</span></label>
                        <select name="priority" id="priority" required
                            class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('priority') border-red-500 @enderror">
                            <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Low</option>
                            <option value="moderate" {{ old('priority') == 'moderate' ? 'selected' : '' }}>Moderate</option>
                            <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>High</option>
                        </select>
                        @error('priority') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                </div>

                <!-- Assign Counselor -->
                <div>
                    <label for="counselor_id" class="block text-sm font-medium text-gray-700 mb-1">Assign to Counselor</label>
                    <select name="counselor_id" id="counselor_id"
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('counselor_id') border-red-500 @enderror">
                        <option value="">Unassigned (Counselor will pick up)</option>
                        @foreach($counselors as $counselor)
                            <option value="{{ $counselor->id }}" {{ old('counselor_id') == $counselor->id ? 'selected' : '' }}>
                                {{ $counselor->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('counselor_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Reason -->
                <div>
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason for Referral <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="reason" rows="4" required placeholder="Describe the concern or reason for referring this student..."
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm @error('reason') border-red-500 @enderror">{{ old('reason', $prefillReason ?? '') }}</textarea>
                    @error('reason') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                <a href="{{ route('admin.referrals.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
                    Cancel
                </a>
                <button type="submit" class="px-5 py-2.5 text-sm font-medium text-white bg-blue-600 rounded-lg hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-send"></i> Submit Referral
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

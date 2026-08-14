@extends('layouts.teacher')

@section('title', 'File Referral')
@section('page-title', 'File New Referral')
@section('page-sub', 'Submit a student to the Guidance Office for intervention (Triggers ML Assessment)')

@section('content')

<div class="mb-6">
    <a href="{{ route('teacher.referrals.index') }}" class="text-sm font-medium text-gray-500 hover:text-blue-600 transition flex items-center gap-1 w-fit">
        <i class="ti ti-arrow-left"></i> Back to My Referrals
    </a>
</div>

<div class="bg-white border border-gray-100 rounded-2xl shadow-sm overflow-hidden max-w-3xl">
    <div class="px-6 py-4 border-b border-gray-100 bg-gray-50/50 flex items-center gap-3">
        <div class="w-8 h-8 rounded-full bg-blue-100 text-blue-600 flex items-center justify-center">
            <i class="ti ti-file-export"></i>
        </div>
        <h3 class="font-semibold text-gray-800">Referral Details</h3>
    </div>
    
    <div class="p-6">
        
        <div class="mb-6 bg-blue-50/50 border border-blue-100 p-4 rounded-xl text-sm text-blue-800 flex gap-3">
            <i class="ti ti-info-circle text-blue-500 text-lg flex-shrink-0 mt-0.5"></i>
            <div>
                <strong class="block mb-1">What happens next?</strong>
                When you submit this referral, the system's ML engine will automatically assess the student's risk level, assign them an appropriate seminar, and instantly send an SMS notification to their parents.
            </div>
        </div>

        <form action="{{ route('teacher.referrals.store') }}" method="POST" class="space-y-6"
              x-data="{ referralType: '{{ old('referral_type') }}' }">
            @csrf
            
            {{-- Student Selection --}}
            <div>
                <label for="student_id" class="block text-sm font-medium text-gray-700 mb-1">Select Student <span class="text-red-500">*</span></label>
                <select name="student_id" id="student_id" required
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                    <option value="" disabled {{ !isset($selectedStudentId) ? 'selected' : '' }}>Select a student from your classes...</option>
                    @foreach($students as $student)
                        <option value="{{ $student->id }}" {{ (old('student_id') == $student->id || (isset($selectedStudentId) && $selectedStudentId == $student->id)) ? 'selected' : '' }}>
                            {{ $student->last_name }}, {{ $student->first_name }} (ID: {{ $student->student_id_number }})
                        </option>
                    @endforeach
                </select>
                @error('student_id') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Referral Type --}}
            <div>
                <label class="block text-sm font-medium text-gray-700 mb-2">Reason for Referral <span class="text-red-500">*</span></label>
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
                        class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">
                </div>
                @error('referral_type') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                @error('referral_type_other') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Specific Details --}}
            <div>
                <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Specific Details & Observations <span class="text-red-500">*</span></label>
                <textarea name="reason" id="reason" rows="5" required placeholder="Please provide specific details about why you are referring this student to the Guidance Office..."
                          class="w-full rounded-lg border-gray-300 focus:border-blue-500 focus:ring focus:ring-blue-200 transition shadow-sm">{{ old('reason') }}</textarea>
                <p class="text-xs text-gray-500 mt-1.5">This information helps the ML engine and the Counselor determine the best intervention.</p>
                @error('reason') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end pt-4 border-t border-gray-100">
                <button type="submit" class="px-6 py-2.5 bg-blue-600 text-white rounded-lg font-medium hover:bg-blue-700 transition shadow-sm flex items-center gap-2">
                    <i class="ti ti-send"></i> Submit Referral
                </button>
            </div>
        </form>
    </div>
</div>

@endsection

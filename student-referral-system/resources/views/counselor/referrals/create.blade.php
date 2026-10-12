@extends('layouts.counselor')

@section('title', 'Create Referral')
@section('page-title', 'Submit New Referral')
@section('page-sub', 'Refer a student to the Guidance Office for intervention')

@section('content')

@php
    $field = 'w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm';
    $border = fn (string $name) => $errors->has($name) ? 'border-red-500' : 'border-gray-300';

    // Student picker: the first 20 students (plus whoever the form arrived
    // with) come with the page; typing searches the rest on the server
    // (admin.students.lookup) instead of loading every student.
    $studentOptions = $students->map->toPickerArray()->values();
@endphp

<div class="bg-white border border-gray-100 rounded-2xl shadow-premium max-w-4xl">
    <div class="px-6 py-5 border-b border-gray-100 bg-gray-50/50 flex items-center justify-between rounded-t-2xl">
        <h3 class="font-semibold text-gray-800 text-lg flex items-center gap-2">
            <i class="ti ti-file-plus text-blue-600"></i> Referral Details
        </h3>
        <a href="{{ route('counselor.referrals.index') }}" class="text-sm font-medium text-gray-500 hover:text-gray-700 flex items-center gap-1">
            <i class="ti ti-arrow-left"></i> Back
        </a>
    </div>

    <div class="p-6">
        <form action="{{ route('counselor.referrals.store') }}" method="POST" x-data="{ referralType: @js((string) old('referral_type')) }">
            @csrf

            <div class="space-y-6">
                <!-- Student (searchable) -->
                <div class="relative"
                     x-data="{
                        open: false,
                        q: '',
                        selected: @js((string) old('student_id', request('student_id'))),
                        students: @js($studentOptions),
                        total: @js($studentTotal),
                        picked: null,
                        loading: false,
                        timer: null,
                        url: @js(route('admin.students.lookup')),
                        init() { this.picked = this.students.find(s => s.id === this.selected) || null; },
                        chosen() { return this.picked; },
                        filtered() { return this.students; },
                        search() { clearTimeout(this.timer); this.timer = setTimeout(() => this.fetchResults(), 250); },
                        async fetchResults() {
                            this.loading = true;
                            try {
                                const res = await fetch(this.url + '?q=' + encodeURIComponent(this.q.trim()), { headers: { Accept: 'application/json' } });
                                const data = await res.json();
                                this.students = data.students;
                                this.total = data.total;
                            } finally { this.loading = false; }
                        },
                        line(s) { return [s.idNo, s.meta].filter(Boolean).join(' · '); },
                        pick(s) { this.selected = s.id; this.picked = s; this.open = false; this.q = ''; }
                     }"
                     @click.outside="open = false" @keydown.escape="open = false" data-student-picker>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Student <span class="text-red-500">*</span></label>
                    <input type="hidden" name="student_id" :value="selected" value="{{ old('student_id') }}">
                    <button type="button" @click="open = ! open; if (open) $nextTick(() => $refs.search.focus())"
                            class="{{ $field }} {{ $border('student_id') }} flex items-center justify-between gap-3 text-left" aria-haspopup="listbox" :aria-expanded="open">
                        <span class="min-w-0">
                            <span class="block truncate" :class="chosen() ? 'text-gray-900' : 'text-gray-400'" x-text="chosen() ? chosen().label : 'Select a student...'">Select a student...</span>
                            <span class="block text-xs text-gray-400 truncate" x-show="chosen()" x-cloak x-text="chosen() ? line(chosen()) : ''"></span>
                        </span>
                        <i class="ti ti-selector text-gray-400 shrink-0"></i>
                    </button>
                    <div x-show="open" x-cloak x-transition.opacity.duration.100ms class="absolute z-30 mt-1 w-full bg-white border border-gray-200 rounded-xl shadow-lg overflow-hidden">
                        <div class="p-2 border-b border-gray-100">
                            <div class="relative">
                                <i class="ti absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 text-sm" :class="loading ? 'ti-loader-2 animate-spin' : 'ti-search'"></i>
                                <input type="search" x-ref="search" x-model="q" @input="search()" placeholder="Search name, ID number or course..." aria-label="Search students"
                                       @keydown.enter.prevent="if (filtered().length) pick(filtered()[0])"
                                       class="block w-full pl-9 pr-3 py-2 border border-gray-200 rounded-lg focus:ring focus:ring-blue-100 focus:border-blue-500 text-sm">
                            </div>
                        </div>
                        <ul class="max-h-64 overflow-y-auto py-1" role="listbox">
                            <template x-for="s in filtered()" :key="s.id">
                                <li>
                                    <button type="button" @click="pick(s)" class="w-full text-left px-4 py-2 hover:bg-blue-50 transition flex items-center justify-between gap-3"
                                            :class="s.id === selected ? 'bg-blue-50/70' : ''" role="option" :aria-selected="s.id === selected">
                                        <span class="min-w-0">
                                            <span class="block text-sm font-medium text-gray-900 truncate" x-text="s.label"></span>
                                            <span class="block text-xs text-gray-500 truncate" x-text="line(s)"></span>
                                        </span>
                                        <i class="ti ti-check text-blue-600" x-show="s.id === selected"></i>
                                    </button>
                                </li>
                            </template>
                            <li x-show="! loading && filtered().length === 0" class="px-4 py-6 text-center text-sm text-gray-400">No active students match "<span x-text="q.trim()"></span>".</li>
                        </ul>
                        <p x-show="total > filtered().length" x-cloak class="px-4 py-2 border-t border-gray-100 text-[11px] text-gray-400" data-picker-more>
                            Showing <span x-text="filtered().length"></span> of <span x-text="total.toLocaleString()"></span> — type to search.
                        </p>
                    </div>
                    @error('student_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Type -->
                <div>
                    <label for="referral_type" class="block text-sm font-medium text-gray-700 mb-1">Referral Type <span class="text-red-500">*</span></label>
                    <select name="referral_type" id="referral_type" required x-model="referralType" class="{{ $field }} {{ $border('referral_type') }}">
                        <option value="">Select type...</option>
                        @foreach(\App\Models\Referral::REFERRAL_TYPES as $type)
                            <option value="{{ $type }}" {{ old('referral_type') == $type ? 'selected' : '' }}>{{ $type }}</option>
                        @endforeach
                    </select>
                    @error('referral_type') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    <div x-show="referralType === 'Other'" x-cloak class="mt-2">
                        <input type="text" name="referral_type_other" value="{{ old('referral_type_other') }}" placeholder="Please specify" class="{{ $field }} {{ $border('referral_type_other') }}">
                        @error('referral_type_other') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Priority is set automatically by the AI risk assessment once submitted.</p>
                </div>

                <!-- Assign Counselor -->
                <div>
                    <label for="counselor_id" class="block text-sm font-medium text-gray-700 mb-1">Assign to Counselor</label>
                    <select name="counselor_id" id="counselor_id" class="{{ $field }} {{ $border('counselor_id') }}">
                        <option value="">Unassigned (Counselor will pick up)</option>
                        @include('partials.counselor-options', ['mode' => 'new', 'selected' => old('counselor_id')])
                    </select>
                    @error('counselor_id') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>

                <!-- Reason -->
                <div>
                    <label for="reason" class="block text-sm font-medium text-gray-700 mb-1">Reason for Referral <span class="text-red-500">*</span></label>
                    <textarea name="reason" id="reason" rows="4" required placeholder="Describe the concern or reason for referring this student..."
                        class="{{ $field }} {{ $border('reason') }}">{{ old('reason') }}</textarea>
                    @error('reason') <p class="mt-1 text-sm text-red-500">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="mt-8 flex items-center justify-end gap-3 pt-5 border-t border-gray-100">
                <a href="{{ route('counselor.referrals.index') }}" class="px-5 py-2.5 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 transition">
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

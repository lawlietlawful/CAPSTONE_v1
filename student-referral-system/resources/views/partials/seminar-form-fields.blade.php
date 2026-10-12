{{--
    The fields of the Create / Edit Seminar forms — one copy for every
    seminar modal (index create + edit, the seminar page's edit), for both
    the counselor and admin views. Expects:

      $seminar        the seminar being edited, or null when creating
      $courseOptions  catalog courses (name, education_level)

    An edit form must also carry <input type="hidden" name="id">: that's how
    a failed submit knows which form its old input and errors belong to.
--}}
@php
    $seminar = $seminar ?? null;
    $useOld = $errors->any() && ($seminar ? (string) old('id') === (string) $seminar->id : ! old('id'));
    $val = fn (string $field, $default = null) => $useOld ? old($field, $default) : $default;
    $bad = fn (string $field) => $useOld && $errors->has($field) ? 'border-red-500' : 'border-gray-300';

    $courseLevels = $courseOptions->mapWithKeys(fn ($c) => [$c->name => $c->education_level]);
    $courseValue = (string) $val('target_course', $seminar?->target_course);
    $yearValue = (string) $val('target_grade_level', $seminar?->target_grade_level);
    // A target saved before the catalog picker existed stays selectable, so
    // editing an old seminar never silently changes who it's for.
    $legacyCourse = $courseValue !== '' && ! $courseLevels->has($courseValue);

    $input = 'w-full rounded-xl border bg-white focus:bg-white focus:border-blue-500 focus:ring focus:ring-blue-200 transition px-4 py-2.5 text-sm text-gray-900 shadow-sm';
@endphp

@if($useOld)
    <div class="mb-5 p-3 rounded-xl bg-red-50 border border-red-100 text-sm text-red-700" data-seminar-form-errors>
        <p class="font-semibold flex items-center gap-1.5"><i class="ti ti-alert-circle"></i> Please fix the following:</p>
        <ul class="mt-1 ml-6 list-disc space-y-0.5">
            @foreach($errors->all() as $message)
                <li>{{ $message }}</li>
            @endforeach
        </ul>
    </div>
@endif

<div class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-6 max-h-[60vh] overflow-y-auto pr-2 custom-scrollbar text-left">

    <!-- Left Column -->
    <div class="space-y-4">
        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
            <i class="ti ti-calendar-event text-blue-600"></i> Seminar Details
        </h4>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Seminar Title <span class="text-red-500">*</span></label>
            <input type="text" name="title" value="{{ $val('title', $seminar?->title) }}" required maxlength="255" class="{{ $input }} {{ $bad('title') }}">
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Date <span class="text-red-500">*</span></label>
                <input type="date" name="date" value="{{ $val('date', $seminar?->date?->format('Y-m-d')) }}" required class="{{ $input }} {{ $bad('date') }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Time <span class="text-red-500">*</span></label>
                <input type="time" name="time" value="{{ $val('time', $seminar ? \Carbon\Carbon::parse($seminar->time)->format('H:i') : null) }}" required class="{{ $input }} {{ $bad('time') }}">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Venue / Room <span class="text-red-500">*</span></label>
            <input type="text" name="venue" value="{{ $val('venue', $seminar?->venue) }}" required maxlength="255" class="{{ $input }} {{ $bad('venue') }}">
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">ML Trigger Reason <span class="text-red-500">*</span></label>
            @php $trigger = $val('trigger_reason', $seminar?->trigger_reason ?? 'general'); @endphp
            <select name="trigger_reason" required class="{{ $input }} {{ $bad('trigger_reason') }}">
                @foreach(\App\Models\Seminar::TRIGGER_REASONS as $value => $label)
                    <option value="{{ $value }}" @selected($trigger === $value)>{{ $label }}</option>
                @endforeach
            </select>
            <p class="mt-1 text-[11px] text-gray-400">Auto-assign and seminar recommendations match at-risk students on this.</p>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Status <span class="text-red-500">*</span></label>
                @php $status = $val('status', $seminar?->status ?? 'upcoming'); @endphp
                <select name="status" required class="{{ $input }} {{ $bad('status') }}">
                    @foreach(\App\Models\Seminar::STATUSES as $option)
                        <option value="{{ $option }}" @selected($status === $option)>{{ ucfirst($option) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Type <span class="text-red-500">*</span></label>
                @php $required = (string) $val('is_required', $seminar ? (int) $seminar->is_required : 1); @endphp
                <select name="is_required" required class="{{ $input }} {{ $bad('is_required') }}">
                    <option value="1" @selected($required === '1')>Required</option>
                    <option value="0" @selected($required === '0')>Optional</option>
                </select>
            </div>
        </div>
    </div>

    <!-- Right Column -->
    <div class="space-y-4">
        <h4 class="font-semibold text-gray-800 border-b border-gray-100 pb-2 flex items-center gap-2 text-sm uppercase tracking-wider">
            <i class="ti ti-users text-blue-600"></i> Audience & Additional
        </h4>

        {{-- Target Year follows the chosen course's education level (College
             year levels or Basic Education grades); with no course, both. --}}
        <div class="grid grid-cols-2 gap-4"
             x-data="{
                course: @js($courseValue),
                year: @js($yearValue),
                levels: @js($courseLevels),
                college: @js(\App\Models\Course::COLLEGE_YEAR_LEVELS),
                basic: @js(\App\Models\Course::BASIC_ED_GRADE_LEVELS),
                years() {
                    const level = this.levels[this.course];
                    if (level === 'Basic Education') return this.basic;
                    if (level === 'College') return this.college;
                    return [...this.college, ...this.basic];
                }
             }">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Target Course</label>
                <select name="target_course" x-model="course" @change="if (! years().includes(year)) year = ''" class="{{ $input }} {{ $bad('target_course') }}">
                    <option value="">All Courses</option>
                    @if($legacyCourse)
                        <option value="{{ $courseValue }}" selected>{{ $courseValue }} (not in catalog)</option>
                    @endif
                    @foreach($courseOptions->groupBy('education_level') as $level => $group)
                        <optgroup label="{{ $level }}">
                            @foreach($group as $course)
                                <option value="{{ $course->name }}" @selected($courseValue === $course->name)>{{ $course->name }}</option>
                            @endforeach
                        </optgroup>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Target Year / Grade</label>
                <select name="target_grade_level" x-model="year" class="{{ $input }} {{ $bad('target_grade_level') }}">
                    <option value="">All Years</option>
                    <template x-for="y in years()" :key="y">
                        <option :value="y" x-text="y" :selected="y === year"></option>
                    </template>
                </select>
            </div>
        </div>

        <div class="grid grid-cols-2 gap-4">
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Speaker</label>
                <input type="text" name="speaker" value="{{ $val('speaker', $seminar?->speaker) }}" maxlength="255" class="{{ $input }} {{ $bad('speaker') }}">
            </div>
            <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Max Capacity</label>
                <input type="number" name="max_participants" value="{{ $val('max_participants', $seminar?->max_participants) }}" min="1" placeholder="Unlimited" class="{{ $input }} {{ $bad('max_participants') }}">
            </div>
        </div>

        <div>
            <label class="block text-xs font-semibold text-gray-600 mb-1">Description</label>
            <textarea name="description" rows="3" class="{{ $input }} {{ $bad('description') }}">{{ $val('description', $seminar?->description) }}</textarea>
        </div>
    </div>
</div>

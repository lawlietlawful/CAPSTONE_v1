<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Seminar;
use App\Models\Student;
use App\Models\TeacherAssignment;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class CourseController extends Controller
{
    public function index()
    {
        $courses = Course::with('sections')->orderBy('name')->get();
        $totalCourses = $courses->count();
        $totalSections = $courses->sum(fn ($course) => $course->sections->count());

        // Student counts per exact (course, grade_level, section) triple —
        // bulk-fetched once so the section list below doesn't run a query
        // per row.
        $studentCounts = Student::select('course', 'grade_level', 'section')
            ->selectRaw('count(*) as total')
            ->groupBy('course', 'grade_level', 'section')
            ->get()
            ->mapWithKeys(fn ($row) => [$this->comboKey($row->course, $row->grade_level, $row->section) => $row->total]);

        // All teacher assignments, fetched once. A teacher's assignment
        // "covers" a section if it matches the course and its grade_level/
        // section are either an exact match or blank (blank means "whole
        // course" or "whole grade level" — see User::advisedStudentsQuery()).
        $teacherAssignments = TeacherAssignment::all(['teacher_id', 'course', 'grade_level', 'section']);

        // Course names any student, teacher assignment or upcoming seminar
        // still uses — such a course's education level is locked (see
        // levelLockReason()).
        $usedCourseNames = Student::distinct()->pluck('course')
            ->merge($teacherAssignments->pluck('course'))
            ->merge(Seminar::whereIn('status', ['upcoming', 'ongoing'])->distinct()->pluck('target_course'))
            ->filter()
            ->mapWithKeys(fn ($name) => [strtolower($name) => true]);

        foreach ($courses as $course) {
            $course->setRelation('sections', $this->sortSections($course->sections));
            $course->level_lock_reason = $this->levelLockReason($course, $usedCourseNames->has(strtolower($course->name)));

            foreach ($course->sections as $section) {
                $key = $this->comboKey($course->name, $section->grade_level, $section->section);
                $section->student_count = (int) $studentCounts->get($key, 0);
                // Case-insensitive, like the student count above — the
                // columns are free text, and MySQL matches them that way too.
                $section->teacher_count = $teacherAssignments
                    ->filter(fn ($a) => strcasecmp((string) $a->course, $course->name) === 0
                        && (! $a->grade_level || strcasecmp($a->grade_level, $section->grade_level) === 0)
                        && (! $a->section || strcasecmp($a->section, $section->section) === 0))
                    ->pluck('teacher_id')
                    ->unique()
                    ->count();
            }
            $course->total_students = $course->sections->sum('student_count');
        }

        $unmatchedStudents = $this->studentsOutsideCatalog($courses);
        $unmatchedAssignments = $this->assignmentsOutsideCatalog($courses);

        return view('admin.courses.index', compact('courses', 'totalCourses', 'totalSections', 'unmatchedStudents', 'unmatchedAssignments'))
            ->with([
                'collegeYearLevels' => Course::COLLEGE_YEAR_LEVELS,
                'basicEdGradeLevels' => Course::BASIC_ED_GRADE_LEVELS,
                'strands' => Course::STRANDS,
                'seniorHighGrades' => Course::SENIOR_HIGH_GRADES,
            ]);
    }

    /**
     * Build the lookup key used to match a Student/TeacherAssignment row
     * (free-text course/grade_level/section columns) against a catalog
     * CourseSection. Case-insensitive so minor casing differences don't
     * silently under-count.
     */
    private function comboKey(?string $course, ?string $gradeLevel, ?string $section): string
    {
        return strtolower(($course ?? '') . '|' . ($gradeLevel ?? '') . '|' . ($section ?? ''));
    }

    /**
     * Students whose course/grade level/section doesn't match any catalog
     * section (or is left blank). They're counted under no section on this
     * page and the section pickers can't select their current placement, so
     * without this list they'd be invisible here. Each gets a reason:
     * the course itself isn't in the catalog, or only the section isn't.
     */
    private function assignmentsOutsideCatalog(Collection $courses): Collection
    {
        // Same idea for teachers: an assignment names a course, and optionally
        // a year level and a section (blank = the whole course / year). It's
        // out of the catalog when what it names doesn't exist — then the
        // teacher's "My Students" list silently matches nobody.
        $byCourse = $courses->keyBy(fn ($c) => strtolower($c->name));

        return TeacherAssignment::with('teacher:id,name')->get()
            ->map(function ($a) use ($byCourse) {
                $course = $byCourse->get(strtolower((string) $a->course));
                $sections = $course?->sections ?? collect();
                $a->catalog_issue = match (true) {
                    ! $course => 'Course not in catalog',
                    $a->grade_level && ! $sections->contains(fn ($s) => strcasecmp($s->grade_level, $a->grade_level) === 0)
                        => 'Year level not in catalog',
                    $a->section && ! $sections->contains(fn ($s) => strcasecmp($s->section, $a->section) === 0
                            && (! $a->grade_level || strcasecmp($s->grade_level, $a->grade_level) === 0))
                        => 'Section not in catalog',
                    default => null,
                };

                return $a;
            })
            ->filter(fn ($a) => $a->catalog_issue !== null)
            ->sortBy(fn ($a) => strtolower($a->teacher?->name ?? ''))
            ->values();
    }

    private function studentsOutsideCatalog(Collection $courses): Collection
    {
        $courseNames = $courses->mapWithKeys(fn ($c) => [strtolower($c->name) => true]);
        $catalogKeys = $courses->flatMap(fn ($c) => $c->sections->map(
            fn ($s) => $this->comboKey($c->name, $s->grade_level, $s->section)
        ))->flip();

        return Student::orderBy('last_name')->orderBy('first_name')
            ->get(['id', 'student_id_number', 'first_name', 'middle_name', 'last_name', 'course', 'grade_level', 'strand', 'section', 'status'])
            ->reject(fn ($s) => $catalogKeys->has($this->comboKey($s->course, $s->grade_level, $s->section)))
            ->each(fn ($s) => $s->catalog_issue = match (true) {
                ! $s->course || ! $s->grade_level || ! $s->section => 'Incomplete placement',
                ! $courseNames->has(strtolower($s->course)) => 'Course not in catalog',
                default => 'Section not in catalog',
            })
            ->values();
    }

    /**
     * Sections in school order: by the grade/year level's position in the
     * fixed lists (so Grade 2 comes before Grade 10, which a plain string
     * sort gets wrong), then strand, then section name naturally ("Block 2"
     * before "Block 10"). A grade level not in either list sorts last.
     */
    private function sortSections(Collection $sections): Collection
    {
        $order = array_flip(array_merge(Course::BASIC_ED_GRADE_LEVELS, Course::COLLEGE_YEAR_LEVELS));

        return $sections->sort(function ($a, $b) use ($order) {
            return [$order[$a->grade_level] ?? PHP_INT_MAX, (string) $a->strand]
                    <=> [$order[$b->grade_level] ?? PHP_INT_MAX, (string) $b->strand]
                ?: strnatcasecmp($a->section, $b->section);
        })->values();
    }

    /**
     * Validation shared by Add Section and Edit Section. The grade level
     * must come from the course's own list (Basic Education grades or
     * College year levels), and a strand is only kept for Basic Education
     * Grade 11/12 — anything else has its strand cleared, so a section
     * moved out of Senior High can't keep a stale "STEM" badge.
     *
     * Duplicates are checked on (grade level, section) without the strand,
     * exactly like the course_sections_unique index: a section is
     * identified by course + grade + section everywhere else in the app
     * (student pickers, teacher assignments, which have no strand at all),
     * so "Grade 11 STEM A" and "Grade 11 ABM A" can't both exist.
     *
     * Errors go to a named bag so the page can reopen the exact form that
     * failed, with the admin's input still in it.
     */
    private function validatedSection(Request $request, Course $course, string $bag, ?int $ignoreId = null): array
    {
        $isBasicEd = $course->education_level === 'Basic Education';
        $gradeLevels = $isBasicEd ? Course::BASIC_ED_GRADE_LEVELS : Course::COLLEGE_YEAR_LEVELS;

        $data = $request->validateWithBag($bag, [
            'grade_level' => ['required', 'string', Rule::in($gradeLevels)],
            'strand' => ['nullable', 'string', Rule::in(Course::STRANDS)],
            'section' => ['required', 'string', 'max:50'],
        ], [
            'grade_level.in' => 'Choose a ' . ($isBasicEd ? 'grade level' : 'year level') . ' from the list.',
            'strand.in' => 'Choose a strand from the list.',
        ]);

        if (! $isBasicEd || ! in_array($data['grade_level'], Course::SENIOR_HIGH_GRADES, true)) {
            $data['strand'] = null;
        }

        $duplicate = CourseSection::where('course_id', $course->id)
            ->where('grade_level', $data['grade_level'])
            ->where('section', $data['section'])
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->first();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'section' => "{$data['grade_level']} — {$duplicate->section} already exists in this course"
                    . ($duplicate->strand ? " (under {$duplicate->strand})" : '')
                    . '. Section names must be unique within a grade level.',
            ])->errorBag($bag);
        }

        return $data;
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:courses,name'],
            'education_level' => ['required', 'string', 'in:Basic Education,College'],
        ]);

        Course::create([
            'name' => $request->name,
            'education_level' => $request->education_level,
        ]);

        return redirect()->route('admin.courses.index')->with('success', 'Course added successfully.');
    }

    /**
     * Rename a course. Student and TeacherAssignment records store the
     * course as free text (not a foreign key), so renaming here would
     * otherwise silently orphan every student/teacher currently under the
     * old name — they're updated in the same transaction to keep the
     * catalog and real records in sync.
     */
    public function update(Request $request, Course $course)
    {
        // Own error bag: a failed rename reopens this course's edit form
        // instead of landing under (and pre-filling) Add Course.
        $request->validateWithBag('renameCourse', [
            'name' => ['required', 'string', 'max:255', 'unique:courses,name,' . $course->id],
            'education_level' => ['nullable', 'string', 'in:Basic Education,College'],
        ]);

        $oldName = $course->name;
        $newName = $request->name;
        $newLevel = $request->education_level ?: $course->education_level;
        $levelChanged = $newLevel !== $course->education_level;

        if ($levelChanged && ($reason = $this->levelLockReason($course->loadCount('sections'), $this->courseInUse($course)))) {
            throw ValidationException::withMessages(['education_level' => $reason])->errorBag('renameCourse');
        }

        if ($oldName === $newName && ! $levelChanged) {
            return redirect()->route('admin.courses.index')->with('success', 'Course updated successfully.');
        }

        if ($oldName === $newName) {
            $course->update(['education_level' => $newLevel]);

            return redirect()->route('admin.courses.index')
                ->with('success', "\"{$newName}\" is now a {$newLevel} course.");
        }

        \App\Models\AuditLog::record('course.renamed', $course, "Renamed course \"{$oldName}\" to \"{$newName}\" (students, teacher assignments and seminar targets updated)");

        DB::transaction(function () use ($course, $oldName, $newName, $newLevel) {
            $course->update(['name' => $newName, 'education_level' => $newLevel]);
            Student::where('course', $oldName)->update(['course' => $newName]);
            TeacherAssignment::where('course', $oldName)->update(['course' => $newName]);
            // Seminars target a course by name too — without this a renamed
            // course's seminars would target a name nobody has any more.
            Seminar::where('target_course', $oldName)->update(['target_course' => $newName]);
        });

        return redirect()->route('admin.courses.index')
            ->with('success', "Course renamed to \"{$newName}\". Existing students, teacher assignments and seminar targets were updated to match.");
    }

    public function destroy(Course $course)
    {
        if ($this->courseInUse($course)) {
            return redirect()->route('admin.courses.index')
                ->with('error', "Cannot delete \"{$course->name}\" — it is still referenced by existing students, teacher assignments or upcoming seminars.");
        }

        \App\Models\AuditLog::record('course.deleted', $course, "Deleted course \"{$course->name}\" and its sections");
        $course->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Course deleted successfully.');
    }

    public function storeSection(Request $request, Course $course)
    {
        $data = $this->validatedSection($request, $course, 'addSection');

        CourseSection::create(['course_id' => $course->id] + $data);

        return redirect()->route('admin.courses.index')->with('success', 'Section added successfully.');
    }

    /**
     * Rename a section's grade level / section label. Like course renames,
     * this cascades to Student and TeacherAssignment rows that reference
     * the exact old grade_level/section text, so they stay matched to the
     * catalog instead of silently falling out of sync.
     */
    public function updateSection(Request $request, CourseSection $courseSection)
    {
        $course = $courseSection->course;
        $data = $this->validatedSection($request, $course, 'editSection', $courseSection->id);

        $oldGradeLevel = $courseSection->grade_level;
        $oldStrand = $courseSection->strand;
        $oldSection = $courseSection->section;
        $newGradeLevel = $data['grade_level'];
        $newStrand = $data['strand'];
        $newSection = $data['section'];

        if ($oldGradeLevel === $newGradeLevel && $oldStrand === $newStrand && $oldSection === $newSection) {
            return redirect()->route('admin.courses.index')->with('success', 'Section updated successfully.');
        }

        DB::transaction(function () use ($courseSection, $course, $oldGradeLevel, $oldStrand, $oldSection, $newGradeLevel, $newStrand, $newSection) {
            $courseSection->update(['grade_level' => $newGradeLevel, 'strand' => $newStrand, 'section' => $newSection]);

            Student::where('course', $course->name)
                ->where('grade_level', $oldGradeLevel)
                ->where('section', $oldSection)
                ->update(['grade_level' => $newGradeLevel, 'strand' => $newStrand, 'section' => $newSection]);

            // Only exact-match assignments reference this section by text;
            // "whole course"/"whole grade level" assignments (null section
            // or null grade_level) don't name this section specifically and
            // are left untouched — they still cover it under the new name.
            TeacherAssignment::where('course', $course->name)
                ->where('grade_level', $oldGradeLevel)
                ->where('section', $oldSection)
                ->update(['grade_level' => $newGradeLevel, 'section' => $newSection]);
        });

        return redirect()->route('admin.courses.index')
            ->with('success', "Section updated to \"{$newGradeLevel} — {$newSection}\". Existing students and teacher assignments were updated to match.");
    }

    public function destroySection(CourseSection $courseSection)
    {
        if ($this->sectionInUse($courseSection)) {
            return redirect()->route('admin.courses.index')
                ->with('error', "Cannot delete \"{$courseSection->section}\" — it is still referenced by existing students or teacher assignments.");
        }

        $courseSection->delete();

        return redirect()->route('admin.courses.index')->with('success', 'Section deleted successfully.');
    }

    /**
     * Why a course's education level can't be changed right now, or null if
     * it can. Its sections use that level's grade/year list, and students
     * carry their own education_level — switching under either would leave
     * "Grade 7" sections on a College course, or students whose level no
     * longer matches their course. So it's only changeable while the
     * course is still empty, which is when a wrong pick actually happens.
     */
    private function levelLockReason(Course $course, bool $inUse): ?string
    {
        $sections = $course->sections_count ?? $course->sections->count();

        return match (true) {
            $sections > 0 => "Remove its {$sections} section" . ($sections === 1 ? '' : 's') . ' before changing the education level.',
            $inUse => 'Students, teacher assignments or upcoming seminars still use this course, so its education level can\'t change.',
            default => null,
        };
    }

    /**
     * Whether any real student, teacher assignment or upcoming seminar still
     * references this course by name, so deleting it wouldn't silently
     * orphan a reference that's still meaningful elsewhere in the system.
     * Past seminars don't count: they keep their target as history.
     */
    private function courseInUse(Course $course): bool
    {
        return Student::where('course', $course->name)->exists()
            || TeacherAssignment::where('course', $course->name)->exists()
            || Seminar::where('target_course', $course->name)->whereIn('status', ['upcoming', 'ongoing'])->exists();
    }

    /**
     * Whether any real student or teacher assignment still references this
     * exact course/grade level/section combination.
     */
    private function sectionInUse(CourseSection $courseSection): bool
    {
        $course = $courseSection->course;

        return Student::where('course', $course->name)
                ->where('grade_level', $courseSection->grade_level)
                ->where('section', $courseSection->section)
                ->exists()
            || TeacherAssignment::where('course', $course->name)
                ->where('grade_level', $courseSection->grade_level)
                ->where('section', $courseSection->section)
                ->exists();
    }
}

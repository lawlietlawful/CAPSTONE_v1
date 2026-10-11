<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Course;
use App\Models\CourseSection;
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

        foreach ($courses as $course) {
            $course->setRelation('sections', $this->sortSections($course->sections));

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

        return view('admin.courses.index', compact('courses', 'totalCourses', 'totalSections'))
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
        // Own error bag: a failed rename reopens this course's rename form
        // instead of landing under (and pre-filling) Add Course.
        $request->validateWithBag('renameCourse', [
            'name' => ['required', 'string', 'max:255', 'unique:courses,name,' . $course->id],
        ]);

        $oldName = $course->name;
        $newName = $request->name;

        if ($oldName === $newName) {
            return redirect()->route('admin.courses.index')->with('success', 'Course updated successfully.');
        }

        DB::transaction(function () use ($course, $oldName, $newName) {
            $course->update(['name' => $newName]);
            Student::where('course', $oldName)->update(['course' => $newName]);
            TeacherAssignment::where('course', $oldName)->update(['course' => $newName]);
        });

        return redirect()->route('admin.courses.index')
            ->with('success', "Course renamed to \"{$newName}\". Existing students and teacher assignments were updated to match.");
    }

    public function destroy(Course $course)
    {
        if ($this->courseInUse($course)) {
            return redirect()->route('admin.courses.index')
                ->with('error', "Cannot delete \"{$course->name}\" — it is still referenced by existing students or teacher assignments.");
        }

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
     * Whether any real student or teacher assignment still references this
     * course by name, so deleting it wouldn't silently orphan a reference
     * that's still meaningful elsewhere in the system.
     */
    private function courseInUse(Course $course): bool
    {
        return Student::where('course', $course->name)->exists()
            || TeacherAssignment::where('course', $course->name)->exists();
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

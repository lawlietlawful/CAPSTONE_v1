<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Courses & Sections (Admin\CourseController + admin/courses/index):
 *
 *  - Adding/editing a section that differed from an existing one only by
 *    strand passed the controller's duplicate check (which included strand)
 *    and then hit the course_sections_unique index (which doesn't) — a 500.
 *  - The strand <select> stayed in the form while hidden, so moving
 *    "Grade 11 STEM A" to Grade 9 saved "Grade 9 · STEM · A".
 *  - Sections were string-sorted: Grade 10, Grade 11, Grade 2.
 *  - A failed course rename showed its error under Add Course (and filled
 *    Add Course with the rejected name) instead of reopening the rename.
 *  - Teacher counts were case-sensitive while student counts weren't.
 */
class CourseCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function basicEd(): Course
    {
        return Course::create(['name' => 'Basic Education', 'education_level' => 'Basic Education']);
    }

    private function college(): Course
    {
        return Course::create(['name' => 'BSIT', 'education_level' => 'College']);
    }

    private function section(Course $course, string $grade, string $section, ?string $strand = null): CourseSection
    {
        return CourseSection::create([
            'course_id' => $course->id, 'grade_level' => $grade, 'strand' => $strand, 'section' => $section,
        ]);
    }

    // ── Strand vs. the unique index ─────────────────────────────────────

    public function test_adding_a_section_that_differs_only_by_strand_is_refused_not_a_500(): void
    {
        $course = $this->basicEd();
        $this->section($course, 'Grade 11', 'A', 'STEM');

        $this->actingAs($this->counselor())
            ->post(route('admin.courses.sections.store', $course->id), [
                '_section_course' => $course->id, 'grade_level' => 'Grade 11', 'strand' => 'ABM', 'section' => 'A',
            ])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('addSection', [
                'section' => 'Grade 11 — A already exists in this course (under STEM). Section names must be unique within a grade level.',
            ]);

        $this->assertSame(1, CourseSection::count());
    }

    public function test_editing_a_section_into_a_strand_only_clash_is_refused_not_a_500(): void
    {
        $course = $this->basicEd();
        $this->section($course, 'Grade 11', 'A', 'STEM');
        $other = $this->section($course, 'Grade 10', 'A');

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.sections.update', $other->id), [
                '_section_id' => $other->id, 'grade_level' => 'Grade 11', 'strand' => 'ABM', 'section' => 'A',
            ])
            ->assertSessionHasErrorsIn('editSection', ['section']);

        $this->assertSame('Grade 10', $other->fresh()->grade_level);
    }

    public function test_an_exact_duplicate_is_refused_too(): void
    {
        $course = $this->college();
        $this->section($course, '1st Year', 'Block 1');

        $this->actingAs($this->counselor())
            ->post(route('admin.courses.sections.store', $course->id), ['grade_level' => '1st Year', 'section' => 'Block 1'])
            ->assertSessionHasErrorsIn('addSection', ['section']);

        $this->assertSame(1, CourseSection::count());
    }

    public function test_editing_a_section_without_changing_it_is_not_a_clash_with_itself(): void
    {
        $course = $this->basicEd();
        $section = $this->section($course, 'Grade 11', 'A', 'STEM');

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.sections.update', $section->id), ['grade_level' => 'Grade 11', 'strand' => 'ABM', 'section' => 'A'])
            ->assertSessionHasNoErrors();

        $this->assertSame('ABM', $section->fresh()->strand);
    }

    // ── Strand only belongs to Basic Education Grade 11/12 ──────────────

    public function test_moving_a_senior_high_section_to_a_lower_grade_clears_its_strand(): void
    {
        $course = $this->basicEd();
        $section = $this->section($course, 'Grade 11', 'A', 'STEM');
        $student = Student::factory()->create([
            'course' => $course->name, 'grade_level' => 'Grade 11', 'strand' => 'STEM', 'section' => 'A',
        ]);

        // What the old form sent: the hidden strand select still submitted.
        $this->actingAs($this->counselor())
            ->put(route('admin.courses.sections.update', $section->id), ['grade_level' => 'Grade 9', 'strand' => 'STEM', 'section' => 'A'])
            ->assertSessionHasNoErrors();

        $this->assertNull($section->fresh()->strand);
        $this->assertSame('Grade 9', $student->fresh()->grade_level);
        $this->assertNull($student->fresh()->strand);
    }

    public function test_a_strand_sent_for_a_college_section_is_dropped(): void
    {
        $course = $this->college();

        $this->actingAs($this->counselor())
            ->post(route('admin.courses.sections.store', $course->id), ['grade_level' => '1st Year', 'strand' => 'STEM', 'section' => 'Block 1'])
            ->assertSessionHasNoErrors();

        $this->assertNull(CourseSection::first()->strand);
    }

    public function test_a_strand_is_kept_for_grade_11_and_12(): void
    {
        $course = $this->basicEd();

        $this->actingAs($this->counselor())
            ->post(route('admin.courses.sections.store', $course->id), ['grade_level' => 'Grade 12', 'strand' => 'HUMSS', 'section' => 'B'])
            ->assertSessionHasNoErrors();

        $this->assertSame('HUMSS', CourseSection::first()->strand);
    }

    public function test_the_grade_level_must_belong_to_the_courses_education_level(): void
    {
        $counselor = $this->counselor();

        $this->actingAs($counselor)
            ->post(route('admin.courses.sections.store', $this->college()->id), ['grade_level' => 'Grade 11', 'section' => 'A'])
            ->assertSessionHasErrorsIn('addSection', ['grade_level' => 'Choose a year level from the list.']);

        $this->actingAs($counselor)
            ->post(route('admin.courses.sections.store', $this->basicEd()->id), ['grade_level' => '1st Year', 'section' => 'A'])
            ->assertSessionHasErrorsIn('addSection', ['grade_level' => 'Choose a grade level from the list.']);

        $this->actingAs($counselor)
            ->post(route('admin.courses.sections.store', Course::where('education_level', 'Basic Education')->first()->id), ['grade_level' => 'Grade 11', 'strand' => 'Cooking', 'section' => 'A'])
            ->assertSessionHasErrorsIn('addSection', ['strand']);

        $this->assertSame(0, CourseSection::count());
    }

    public function test_the_hidden_strand_select_is_disabled_so_it_is_not_submitted(): void
    {
        $course = $this->basicEd();
        $this->section($course, 'Grade 11', 'A', 'STEM');

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertStringContainsString(':disabled="!(isBasicEd() && isSeniorHigh(gradeLevel))"', $html);
        $this->assertStringContainsString(':disabled="!(isBasicEd() && isSeniorHigh(editGradeLevel))"', $html);
    }

    // ── Order ───────────────────────────────────────────────────────────

    public function test_sections_are_listed_in_school_order_not_string_order(): void
    {
        $course = $this->basicEd();
        $this->section($course, 'Grade 10', 'A');
        $this->section($course, 'Grade 11', 'A', 'STEM');
        $this->section($course, 'Grade 2', 'Block 10');
        $this->section($course, 'Grade 2', 'Block 2');
        $this->section($course, 'Kindergarten', 'A');

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertSame(
            ['Kindergarten-A', 'Grade 2-Block 2', 'Grade 2-Block 10', 'Grade 10-A', 'Grade 11 STEM-A'],
            $this->deleteLabels($html)
        );
    }

    public function test_college_year_levels_are_in_order_too(): void
    {
        $course = $this->college();
        $this->section($course, 'Irregular', 'A');
        $this->section($course, '2nd Year', 'A');
        $this->section($course, '1st Year', 'A');

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertSame(['1st Year-A', '2nd Year-A', 'Irregular-A'], $this->deleteLabels($html));
    }

    /** The "grade - section" labels in each section's delete confirmation, in page order. */
    private function deleteLabels(string $html): array
    {
        preg_match_all('/delete \\\\u0022(.+?) - (.+?)\\\\u0022\?/', $html, $m, PREG_SET_ORDER);

        return array_map(fn ($x) => $x[1] . '-' . $x[2], $m);
    }

    // ── Rename errors stay on the rename form ───────────────────────────

    public function test_a_rename_clash_goes_to_its_own_error_bag(): void
    {
        $this->college();
        $nursing = Course::create(['name' => 'BSN', 'education_level' => 'College']);

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $nursing->id), ['name' => 'BSIT'])
            ->assertSessionHasErrorsIn('renameCourse', ['name'])
            ->assertSessionDoesntHaveErrors(['name']);   // default bag = Add Course
    }

    public function test_a_failed_rename_reopens_the_rename_form_not_add_course(): void
    {
        $this->college();
        $nursing = Course::create(['name' => 'BSN', 'education_level' => 'College']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)
            ->from(route('admin.courses.index'))
            ->put(route('admin.courses.update', $nursing->id), ['_rename_course' => $nursing->id, 'name' => 'BSIT']);

        $html = $this->actingAs($counselor)->get(route('admin.courses.index'))->getContent();

        // Add Course isn't pre-filled with the rejected name…
        $this->assertStringContainsString('name="name" value="" placeholder="e.g. BSIT', $html);
        // …the rename form is reopened with it, and the error shows there.
        $this->assertStringContainsString('editingCourse: true', $html);
        $this->assertStringContainsString('name="name" value="BSIT" required', $html);
        // Shown once — on the rename form, not also under Add Course.
        $this->assertSame(1, substr_count($html, 'The name has already been taken.'));
        $this->assertGreaterThan(strpos($html, '_rename_course" value="' . $nursing->id), strpos($html, 'The name has already been taken.'));
        $this->assertSame('BSN', $nursing->fresh()->name);
    }

    public function test_a_failed_add_section_reopens_that_courses_form_with_the_input(): void
    {
        $course = $this->basicEd();
        $this->section($course, 'Grade 11', 'A', 'STEM');
        $counselor = $this->counselor();

        $this->actingAs($counselor)
            ->from(route('admin.courses.index'))
            ->post(route('admin.courses.sections.store', $course->id), [
                '_section_course' => $course->id, 'grade_level' => 'Grade 11', 'strand' => 'ABM', 'section' => 'A',
            ]);

        $html = $this->actingAs($counselor)->get(route('admin.courses.index'))->getContent();

        $this->assertStringContainsString('addingSection: true', $html);
        $this->assertStringContainsString("gradeLevel: 'Grade 11'", $html);
        $this->assertStringContainsString('<option value="ABM" selected>', $html);
        $this->assertStringContainsString('already exists in this course (under STEM)', $html);
    }

    public function test_a_successful_rename_still_cascades(): void
    {
        $course = $this->college();
        $student = Student::factory()->create(['course' => 'BSIT', 'grade_level' => '1st Year', 'section' => 'A']);
        $assignment = TeacherAssignment::factory()->create(['course' => 'BSIT']);

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $course->id), ['name' => 'Bachelor of Science in Information Technology'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Bachelor of Science in Information Technology', $student->fresh()->course);
        $this->assertSame('Bachelor of Science in Information Technology', $assignment->fresh()->course);
    }

    // ── Counts ──────────────────────────────────────────────────────────

    public function test_teacher_counts_ignore_case_like_student_counts(): void
    {
        $course = $this->college();
        $this->section($course, '1st Year', 'Block 1');
        Student::factory()->create(['course' => 'bsit', 'grade_level' => '1st year', 'section' => 'block 1']);
        TeacherAssignment::factory()->create(['course' => 'bsit', 'grade_level' => '1st year', 'section' => 'block 1']);

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertMatchesRegularExpression('/ti-users"><\/i>\s*1 student\b/', $html);
        $this->assertMatchesRegularExpression('/ti-user-check"><\/i>\s*1 teacher\b/', $html);
    }

    // ── Escaping ────────────────────────────────────────────────────────

    public function test_a_name_with_quotes_cannot_break_out_of_the_delete_confirmation(): void
    {
        $course = Course::create(['name' => "St. Mary's \"Main\" Campus", 'education_level' => 'College']);

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        // Both quote kinds arrive hex-escaped inside the JS string literal…
        $escaped = str_replace(["'", '"'], ['\\' . 'u0027', '\\' . 'u0022'], $course->name);
        $this->assertStringContainsString("delete \\u0022{$escaped}\\u0022?", $html);
        // …and never raw, where the apostrophe would end the string.
        $this->assertStringNotContainsString("St. Mary's", $html);
    }
}

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

    // ── Changing a course's education level ─────────────────────────────

    public function test_an_empty_courses_education_level_can_be_changed(): void
    {
        $course = Course::create(['name' => 'Junior High', 'education_level' => 'College']);   // the wrong pick

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $course->id), ['name' => 'Junior High', 'education_level' => 'Basic Education'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', '"Junior High" is now a Basic Education course.');

        $this->assertSame('Basic Education', $course->fresh()->education_level);
    }

    public function test_a_rename_and_level_change_can_happen_together(): void
    {
        $course = Course::create(['name' => 'Junoir High', 'education_level' => 'College']);

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $course->id), ['name' => 'Junior High', 'education_level' => 'Basic Education'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['Junior High', 'Basic Education'], [$course->fresh()->name, $course->fresh()->education_level]);
    }

    public function test_the_level_is_locked_once_the_course_has_sections(): void
    {
        $course = $this->college();
        $this->section($course, '1st Year', 'Block 1');

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $course->id), ['name' => 'BSIT', 'education_level' => 'Basic Education'])
            ->assertSessionHasErrorsIn('renameCourse', [
                'education_level' => 'Remove its 1 section before changing the education level.',
            ]);

        $this->assertSame('College', $course->fresh()->education_level);
    }

    public function test_the_level_is_locked_while_students_use_the_course(): void
    {
        $course = $this->college();
        Student::factory()->create(['course' => 'BSIT', 'grade_level' => '2nd Year', 'section' => 'Z']);

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $course->id), ['name' => 'BSIT', 'education_level' => 'Basic Education'])
            ->assertSessionHasErrorsIn('renameCourse', ['education_level']);

        $this->assertSame('College', $course->fresh()->education_level);
    }

    public function test_a_locked_course_can_still_be_renamed(): void
    {
        // The disabled select isn't submitted, so no education_level arrives.
        $course = $this->college();
        $this->section($course, '1st Year', 'Block 1');

        $this->actingAs($this->counselor())
            ->put(route('admin.courses.update', $course->id), ['name' => 'BS Information Technology'])
            ->assertSessionHasNoErrors();

        $this->assertSame(['BS Information Technology', 'College'], [$course->fresh()->name, $course->fresh()->education_level]);
    }

    public function test_the_edit_form_shows_the_level_select_disabled_with_the_reason_only_when_locked(): void
    {
        $locked = $this->college();
        $this->section($locked, '1st Year', 'Block 1');
        $open = Course::create(['name' => 'New Program', 'education_level' => 'College']);

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertMatchesRegularExpression('/<select name="education_level" disabled title="Remove its 1 section before changing the education level\."/', $html);
        $this->assertStringContainsString('<select name="education_level"  title="Education level"', $html);
        $this->assertSame(1, substr_count($html, 'ti-lock'));
    }

    // ── Search ──────────────────────────────────────────────────────────

    public function test_the_search_index_covers_names_abbreviations_levels_and_sections_in_display_order(): void
    {
        $course = Course::create(['name' => 'Bachelor of Science in Information Technology', 'education_level' => 'College']);
        $this->section($course, '2nd Year', 'Block 1');
        $this->section($course, '1st Year', 'Block 1');
        $shs = $this->basicEd();
        $this->section($shs, 'Grade 11', 'A', 'STEM');

        $response = $this->actingAs($this->counselor())->get(route('admin.courses.index'));

        // Courses by name, sections in school order (1st Year before 2nd).
        $this->assertSame([
            ['course' => 'bachelor of science in information technology | bsit | college', 'sections' => ['1st year block 1 | 1st year block 1', '2nd year block 1 | 2nd year block 1']],
            ['course' => 'basic education | bed | basic education', 'sections' => ['grade 11 stem a | grade 11 a']],
        ], $this->catalogFromJs($response->getContent()));

        // Cards and rows are bound to it by position.
        $response->assertSee('x-show="cardHit(0)"', false)
            ->assertSee('x-show="cardHit(1)"', false)
            ->assertSee('x-show="sectionHit(0, 1)"', false);
    }

    /** Decode the `catalog: …` value @js() rendered (a JSON.parse('…') call with \uXXXX escapes). */
    private function catalogFromJs(string $html): ?array
    {
        if (! preg_match("/catalog: JSON\\.parse\\('(.+?)'\\)/", $html, $m)) {
            return null;
        }

        return json_decode(json_decode('"' . $m[1] . '"'), true);
    }

    public function test_no_search_box_when_there_are_no_courses(): void
    {
        $this->actingAs($this->counselor())->get(route('admin.courses.index'))
            ->assertDontSee('Search courses or sections', false)
            ->assertSee('No courses yet');
    }

    // ── Students outside the catalog ────────────────────────────────────

    public function test_students_outside_the_catalog_are_listed_with_the_reason(): void
    {
        $course = $this->college();
        $this->section($course, '1st Year', 'Block 1');

        Student::factory()->create(['first_name' => 'Ina', 'last_name' => 'Matched', 'course' => 'bsit', 'grade_level' => '1st Year', 'section' => 'BLOCK 1']);
        $noCourse = Student::factory()->create(['first_name' => 'Riskyn', 'last_name' => 'Testcase', 'course' => 'BSCS', 'grade_level' => '2nd Year', 'section' => 'A']);
        $noSection = Student::factory()->create(['first_name' => 'Olo', 'last_name' => 'Blocktwo', 'course' => 'BSIT', 'grade_level' => '1st Year', 'section' => 'Block 2']);

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertStringContainsString('2 students don&#039;t match any section in the catalog', $html);
        $this->assertStringContainsString('Testcase, Riskyn', $html);
        $this->assertStringContainsString('Course not in catalog', $html);
        $this->assertStringContainsString('Blocktwo, Olo', $html);
        $this->assertStringContainsString('Section not in catalog', $html);
        // Case differences alone don't make a student "unmatched".
        $this->assertStringNotContainsString('Matched, Ina', $html);
        // Each links straight to that student's edit form.
        $this->assertStringContainsString(route('admin.students.show', ['student' => $noCourse->id, 'edit' => 1]), $html);
        $this->assertStringContainsString(route('admin.students.show', ['student' => $noSection->id, 'edit' => 1]), $html);
    }

    public function test_the_unmatched_panel_is_hidden_when_every_student_matches(): void
    {
        $course = $this->college();
        $this->section($course, '1st Year', 'Block 1');
        Student::factory()->create(['course' => 'BSIT', 'grade_level' => '1st Year', 'section' => 'Block 1']);

        $html = $this->actingAs($this->counselor())->get(route('admin.courses.index'))->getContent();

        $this->assertStringNotContainsString('data-unmatched-students', $html);
    }

    public function test_fix_placement_opens_the_students_edit_form(): void
    {
        $student = Student::factory()->create();
        $counselor = $this->counselor();

        $this->actingAs($counselor)->get(route('admin.students.show', ['student' => $student->id, 'edit' => 1]))
            ->assertSee('showEditModal: true', false);
        $this->actingAs($counselor)->get(route('admin.students.show', $student->id))
            ->assertSee('showEditModal: false', false);
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

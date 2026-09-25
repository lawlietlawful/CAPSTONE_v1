<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Findings from the Student page audit: CSV import failures were silent, the
 * Student page was a dead end (no risk profile / intervention links, no
 * interventions in the timeline), full-name search found nothing, a student
 * with open cases could be deleted with all their history, and raw database
 * errors were shown to the user.
 */
class StudentFlowFixesTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'student_id_number', 'course', 'education_level', 'first_name', 'last_name', 'middle_name',
        'gender', 'birthdate', 'grade_level', 'strand', 'section', 'school_year',
        'parent_name', 'parent_contact', 'parent_email', 'student_contact',
        'address', 'status',
    ];

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function seedCatalog(): void
    {
        $course = Course::create(['name' => 'BSIT', 'education_level' => 'College']);
        CourseSection::create(['course_id' => $course->id, 'grade_level' => '1st Year', 'section' => 'A']);
    }

    private function row(array $overrides = []): array
    {
        return array_merge([
            'student_id_number' => '2024-0001', 'course' => 'BSIT', 'education_level' => 'College',
            'first_name' => 'Juan', 'last_name' => 'Dela Cruz', 'middle_name' => 'Santos', 'gender' => 'Male',
            'birthdate' => '2005-06-15', 'grade_level' => '1st Year', 'strand' => '', 'section' => 'A',
            'school_year' => '2025-2026', 'parent_name' => 'Maria', 'parent_contact' => '09171234567',
            'parent_email' => 'maria@example.com', 'student_contact' => '09181234567', 'address' => '123 Sample St', 'status' => 'active',
        ], $overrides);
    }

    private function csvFile(array $rows): UploadedFile
    {
        $lines = [implode(',', self::HEADER)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($col) => $row[$col], self::HEADER));
        }

        return UploadedFile::fake()->createWithContent('students.csv', implode("\n", $lines));
    }

    private function stageImport(array $validRows): string
    {
        foreach ($validRows as $i => &$row) {
            $row['_row'] = $i + 2;
        }
        Cache::put('import_flow', ['valid' => $validRows, 'duplicate' => [], 'invalid' => [], 'header' => self::HEADER], now()->addHour());

        return 'flow';
    }

    private function commit(User $by, string $importId)
    {
        return $this->actingAs($by)->postJson(route('admin.students.import.commit'), ['import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1]);
    }

    // ── Import: preview ──────────────────────────────────────────────────

    public function test_preview_flags_a_student_id_already_used_as_another_accounts_login(): void
    {
        $this->seedCatalog();
        User::factory()->teacher()->create(['username' => '2024-0001']);

        $response = $this->actingAs($this->counselor())
            ->postJson(route('admin.students.import.preview'), ['csv_file' => $this->csvFile([$this->row()])])
            ->assertOk();

        $response->assertJson(['summary' => ['valid' => 0, 'invalid' => 1]]);
        $this->assertStringContainsString('already used as a login', $response->json('invalid_preview.0._errors.0'));
    }

    public function test_preview_still_treats_an_existing_students_own_login_as_a_duplicate_not_an_error(): void
    {
        $this->seedCatalog();
        $student = Student::factory()->create(['student_id_number' => '2024-0001']);
        $student->user->update(['username' => '2024-0001']);

        $this->actingAs($this->counselor())
            ->postJson(route('admin.students.import.preview'), ['csv_file' => $this->csvFile([$this->row()])])
            ->assertOk()
            ->assertJson(['summary' => ['valid' => 0, 'duplicate' => 1, 'invalid' => 0]]);
    }

    // ── Import: commit reporting ─────────────────────────────────────────

    public function test_commit_reports_a_row_that_failed_instead_of_claiming_success(): void
    {
        User::factory()->teacher()->create(['username' => '2099-0001']); // login collision the preview would now catch
        $id = $this->stageImport([$this->row(['student_id_number' => '2099-0001'])]);

        $json = $this->commit($this->counselor(), $id)->assertOk()->json();

        $this->assertSame(0, Student::where('student_id_number', '2099-0001')->count());
        $this->assertSame(0, $json['imported']);
        $this->assertSame(1, $json['failed']);
        $this->assertSame(2, $json['failed_preview'][0]['row']);
        $this->assertSame('2099-0001', $json['failed_preview'][0]['student_id_number']);
        $this->assertStringContainsString('already exists', $json['failed_preview'][0]['reason']);
    }

    public function test_one_bad_row_does_not_stop_the_good_ones(): void
    {
        User::factory()->teacher()->create(['username' => '2099-0001']);
        $id = $this->stageImport([
            $this->row(['student_id_number' => '2099-0001']),
            $this->row(['student_id_number' => '2099-0002', 'first_name' => 'Good']),
        ]);

        $json = $this->commit($this->counselor(), $id)->json();

        $this->assertSame(1, $json['imported']);
        $this->assertSame(1, $json['failed']);
        $this->assertSame(1, $json['codes_generated']);
        $this->assertSame(1, Student::where('student_id_number', '2099-0002')->count());
    }

    public function test_a_row_that_fails_after_its_login_was_created_leaves_no_phantom_activation_code(): void
    {
        // middle_name is longer than its column: the login is created first, then the student insert fails.
        $id = $this->stageImport([$this->row(['student_id_number' => '2099-0003', 'middle_name' => str_repeat('x', 400)])]);

        $json = $this->commit($this->counselor(), $id)->json();

        $this->assertSame(1, $json['failed']);
        $this->assertSame(0, $json['codes_generated'], 'no code for an account that was rolled back');
        $this->assertSame(0, User::where('username', '2099-0003')->count());
    }

    public function test_the_error_report_includes_rows_that_failed_at_commit_with_a_reason(): void
    {
        User::factory()->teacher()->create(['username' => '2099-0001']);
        $id = $this->stageImport([$this->row(['student_id_number' => '2099-0001'])]);
        $this->commit($this->counselor(), $id);

        $csv = $this->actingAs($this->counselor())->get(route('admin.students.import.errors', $id))->streamedContent();

        $this->assertStringContainsString('error_reason', $csv);
        $this->assertStringContainsString('2099-0001', $csv);
        $this->assertStringContainsString('already exists', $csv);
    }

    public function test_a_clean_import_reports_no_failures(): void
    {
        $id = $this->stageImport([$this->row(['student_id_number' => '2099-0009'])]);

        $json = $this->commit($this->counselor(), $id)->json();

        $this->assertSame(1, $json['imported']);
        $this->assertSame(0, $json['failed']);
        $this->assertSame([], $json['failed_preview']);
    }

    // ── Student page hub ─────────────────────────────────────────────────

    public function test_the_student_page_links_to_the_risk_profile_only_when_assessed(): void
    {
        $assessed = Student::factory()->create();
        RiskAssessment::create(['student_id' => $assessed->id, 'risk_score' => 90, 'risk_level' => 'high', 'assessed_at' => now()]);
        $bare = Student::factory()->create();
        $c = $this->counselor();

        $this->assertStringContainsString(route('admin.risk.show', $assessed->id), $this->actingAs($c)->get(route('admin.students.show', $assessed->id))->getContent());
        $this->assertStringNotContainsString(route('admin.risk.show', $bare->id), $this->actingAs($c)->get(route('admin.students.show', $bare->id))->getContent());
    }

    public function test_log_intervention_links_to_the_open_referral_and_the_form_preselects_it(): void
    {
        $c = $this->counselor();
        $student = Student::factory()->create();
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'resolved']);
        $open = Referral::factory()->create(['student_id' => $student->id, 'status' => 'in_progress']);

        $html = $this->actingAs($c)->get(route('admin.students.show', $student->id))->getContent();
        $this->assertStringContainsString(e(route('counselor.interventions.create', ['referral_id' => $open->id])), $html);

        $form = $this->actingAs($c)->get(route('counselor.interventions.create', ['referral_id' => $open->id]))->getContent();
        $this->assertMatchesRegularExpression('/<option value="' . $open->id . '"\s+selected/', $form);
    }

    public function test_log_intervention_is_disabled_when_the_student_has_no_open_referral(): void
    {
        $student = Student::factory()->create();
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'resolved']);

        $html = $this->actingAs($this->counselor())->get(route('admin.students.show', $student->id))->getContent();

        $this->assertStringContainsString('An intervention is logged against an open referral', $html);
        $this->assertStringNotContainsString(route('counselor.interventions.create'), $html);
    }

    public function test_the_timeline_includes_interventions_and_labels_the_risk_source(): void
    {
        $c = User::factory()->counselor()->create(['name' => 'Ma\'am Timeline']);
        $student = Student::factory()->create();
        $referral = Referral::factory()->create(['student_id' => $student->id]);
        Intervention::factory()->create([
            'referral_id' => $referral->id, 'counselor_id' => $c->id,
            'intervention_type' => 'Behavioral Contract', 'outcome' => 'improving',
        ]);
        RiskAssessment::create([
            'student_id' => $student->id, 'risk_score' => 20, 'risk_level' => 'low', 'assessed_at' => now(),
            'risk_factors' => ['source' => 'override', 'override' => ['by_name' => 'Ma\'am Reviewer', 'note' => 'Met the family']],
        ]);

        $html = $this->actingAs($c)->get(route('admin.students.show', $student->id))->getContent();

        $this->assertStringContainsString('Intervention: Behavioral Contract', $html);
        $this->assertStringContainsString('improving', $html);
        $this->assertStringContainsString('Manual review by', $html);
        $this->assertStringContainsString('Met the family', $html);
    }

    public function test_the_timeline_only_shows_this_students_interventions(): void
    {
        $student = Student::factory()->create();
        Intervention::factory()->create(['intervention_type' => 'Academic Coaching']); // someone else's

        $html = $this->actingAs($this->counselor())->get(route('admin.students.show', $student->id))->getContent();

        $this->assertStringNotContainsString('Intervention: Academic Coaching', $html);
    }

    // ── Search ───────────────────────────────────────────────────────────

    public function test_the_student_list_finds_a_full_name_in_either_order(): void
    {
        Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Student::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Reyes']);
        $c = $this->counselor();

        foreach (['Maria Santos', 'Santos Maria', 'Santos, Maria', 'santos', '  Maria  '] as $term) {
            $this->assertSame(1, $this->actingAs($c)->get(route('admin.students.index', ['search' => $term]))->viewData('students')->total(), "search '{$term}'");
        }
    }

    public function test_the_student_list_still_searches_by_id_number_and_blank_returns_everyone(): void
    {
        $a = Student::factory()->create(['student_id_number' => '2026-1234']);
        Student::factory()->create(['student_id_number' => '2026-9999']);
        $c = $this->counselor();

        $this->assertSame(1, $this->actingAs($c)->get(route('admin.students.index', ['search' => '1234']))->viewData('students')->total());
        $this->assertSame(2, $this->actingAs($c)->get(route('admin.students.index'))->viewData('students')->total());
    }

    // ── Delete guard ─────────────────────────────────────────────────────

    public function test_a_student_with_an_open_referral_cannot_be_deleted(): void
    {
        $student = Student::factory()->create(['first_name' => 'Guarded']);
        $referral = Referral::factory()->create(['student_id' => $student->id, 'status' => 'pending']);

        $this->actingAs($this->counselor())->delete(route('admin.students.destroy', $student->id))->assertSessionHas('error');

        $this->assertDatabaseHas('students', ['id' => $student->id]);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id]);
        $this->assertStringContainsString('open referral', session('error'));
        $this->assertStringContainsString('Inactive, Graduated or Transferred', session('error'));
    }

    public function test_a_student_with_only_closed_referrals_can_be_deleted(): void
    {
        $student = Student::factory()->create();
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'resolved']);
        Referral::factory()->create(['student_id' => $student->id, 'status' => 'cancelled']);

        $this->actingAs($this->counselor())->delete(route('admin.students.destroy', $student->id))->assertSessionHas('success');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    public function test_a_student_with_no_referrals_can_be_deleted(): void
    {
        $student = Student::factory()->create();

        $this->actingAs($this->counselor())->delete(route('admin.students.destroy', $student->id))->assertSessionHas('success');

        $this->assertDatabaseMissing('students', ['id' => $student->id]);
    }

    // ── Error messages ───────────────────────────────────────────────────

    public function test_a_failed_create_shows_a_friendly_message_not_the_database_error(): void
    {
        $this->seedCatalog();
        User::factory()->teacher()->create(['username' => '2088-0001']); // the new student's login would collide

        $response = $this->actingAs($this->counselor())->post(route('admin.students.store'), [
            'student_id_number' => '2088-0001', 'course' => 'BSIT', 'education_level' => 'College',
            'first_name' => 'Clash', 'last_name' => 'Test', 'gender' => 'Male', 'birthdate' => '2005-01-01',
            'grade_level' => '1st Year', 'section' => 'A', 'school_year' => '2025-2026',
            'parent_name' => 'P', 'parent_contact' => '09171234567', 'address' => 'X', 'status' => 'active',
        ]);

        $response->assertSessionHas('error');
        $this->assertStringContainsString('could not be created', session('error'));
        $this->assertStringNotContainsString('SQLSTATE', session('error'));
        $this->assertDatabaseMissing('students', ['student_id_number' => '2088-0001']);
    }
}

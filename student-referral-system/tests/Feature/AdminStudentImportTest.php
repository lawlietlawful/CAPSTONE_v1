<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * The bulk Student CSV importer (Admin\StudentController::previewImport /
 * commitImport / downloadImportErrors): row classification (valid/duplicate/
 * invalid), the course/section catalog check, the skip-vs-update duplicate
 * strategy, chunked commit pagination, and the error-report download.
 */
class AdminStudentImportTest extends TestCase
{
    use RefreshDatabase;

    private const HEADER = [
        'student_id_number', 'course', 'education_level', 'first_name', 'last_name', 'middle_name',
        'gender', 'birthdate', 'grade_level', 'strand', 'section', 'school_year',
        'parent_name', 'parent_contact', 'parent_email', 'student_contact',
        'address', 'status',
    ];

    /** Seed a catalog entry so BSIT / 1st Year / A passes the course/section check. */
    private function seedCatalog(): void
    {
        $course = Course::create(['name' => 'BSIT', 'education_level' => 'College']);
        CourseSection::create(['course_id' => $course->id, 'grade_level' => '1st Year', 'section' => 'A']);
    }

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    /** One CSV data row, in HEADER column order, with sensible defaults. */
    private function row(array $overrides = []): array
    {
        $defaults = [
            'student_id_number' => '2024-0001',
            'course' => 'BSIT',
            'education_level' => 'College',
            'first_name' => 'Juan',
            'last_name' => 'Dela Cruz',
            'middle_name' => 'Santos',
            'gender' => 'Male',
            'birthdate' => '2005-06-15',
            'grade_level' => '1st Year',
            'strand' => '',
            'section' => 'A',
            'school_year' => '2025-2026',
            'parent_name' => 'Maria Dela Cruz',
            'parent_contact' => '09171234567',
            'parent_email' => 'maria@example.com',
            'student_contact' => '09181234567',
            'address' => '123 Sample St. Cagayan de Oro City',
            'status' => 'active',
        ];

        return array_merge($defaults, $overrides);
    }

    private function csvFile(array $rows): UploadedFile
    {
        $lines = [implode(',', self::HEADER)];
        foreach ($rows as $row) {
            $lines[] = implode(',', array_map(fn ($col) => $row[$col], self::HEADER));
        }

        return UploadedFile::fake()->createWithContent('students.csv', implode("\n", $lines));
    }

    // ── Access gate ───────────────────────────────────────────────────────

    public function test_non_admin_cannot_preview_an_import(): void
    {
        $teacher = User::factory()->teacher()->create();
        $this->seedCatalog();

        $this->actingAs($teacher)
            ->post(route('admin.students.import.preview'), ['csv_file' => $this->csvFile([$this->row()])])
            ->assertForbidden();
    }

    // ── Preview: classification ──────────────────────────────────────────

    public function test_preview_classifies_a_clean_row_as_valid(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())
            ->postJson(route('admin.students.import.preview'), ['csv_file' => $this->csvFile([$this->row()])])
            ->assertOk();

        $response->assertJson(['summary' => ['valid' => 1, 'duplicate' => 0, 'invalid' => 0, 'total' => 1]]);
        $this->assertDatabaseCount('students', 0); // preview never writes to the DB
    }

    public function test_preview_rejects_a_file_missing_required_columns(): void
    {
        $file = UploadedFile::fake()->createWithContent('students.csv', "student_id_number,first_name\n2024-0001,Juan");

        $this->actingAs($this->counselor())
            ->postJson(route('admin.students.import.preview'), ['csv_file' => $file])
            ->assertStatus(400)
            ->assertJsonStructure(['error']);
    }

    public function test_preview_flags_a_course_section_combo_not_in_the_catalog(): void
    {
        // No seedCatalog() call — BSIT / 1st Year / A is not in the catalog.
        $response = $this->actingAs($this->counselor())
            ->postJson(route('admin.students.import.preview'), ['csv_file' => $this->csvFile([$this->row()])])
            ->assertOk();

        $response->assertJson(['summary' => ['valid' => 0, 'invalid' => 1]]);
    }

    public function test_preview_flags_a_row_with_an_invalid_enum_value(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())->postJson(
            route('admin.students.import.preview'),
            ['csv_file' => $this->csvFile([$this->row(['gender' => 'Other'])])]
        )->assertOk();

        $response->assertJson(['summary' => ['valid' => 0, 'invalid' => 1]]);
    }

    public function test_preview_flags_a_student_id_already_in_the_database_as_duplicate(): void
    {
        $this->seedCatalog();
        Student::factory()->create(['student_id_number' => '2024-0001']);

        $response = $this->actingAs($this->counselor())->postJson(
            route('admin.students.import.preview'),
            ['csv_file' => $this->csvFile([$this->row(['student_id_number' => '2024-0001'])])]
        )->assertOk();

        $response->assertJson(['summary' => ['valid' => 0, 'duplicate' => 1, 'invalid' => 0]]);
    }

    public function test_preview_flags_a_student_id_repeated_within_the_same_file(): void
    {
        $this->seedCatalog();

        $response = $this->actingAs($this->counselor())->postJson(
            route('admin.students.import.preview'),
            ['csv_file' => $this->csvFile([
                $this->row(['student_id_number' => '2024-0001']),
                $this->row(['student_id_number' => '2024-0001', 'first_name' => 'Pedro']),
            ])]
        )->assertOk();

        // First occurrence is valid; the second is invalid (duplicated earlier in file).
        $response->assertJson(['summary' => ['valid' => 1, 'invalid' => 1]]);
    }

    // ── Commit ────────────────────────────────────────────────────────────

    public function test_commit_creates_a_locked_user_account_and_student_record(): void
    {
        $this->seedCatalog();
        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [$this->row(['_row' => 2])],
            'duplicate' => [], 'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $this->actingAs($this->counselor())->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1,
        ])->assertOk()->assertJson(['success' => true, 'current_page' => 1, 'total_pages' => 1, 'progress' => 100]);

        $student = Student::where('student_id_number', '2024-0001')->first();
        $this->assertNotNull($student);
        $this->assertSame('Juan', $student->first_name);

        $user = $student->user;
        $this->assertSame('student', $user->role);
        $this->assertNull($user->account_activated_at, 'imported accounts must start locked/unactivated');
        $this->assertNotNull($user->activation_code, 'imported accounts must get a one-time activation code');
    }

    public function test_commit_response_reports_how_many_activation_codes_were_generated(): void
    {
        $this->seedCatalog();
        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [$this->row(['_row' => 2])],
            'duplicate' => [], 'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $this->actingAs($this->counselor())->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1,
        ])->assertOk()->assertJson(['codes_generated' => 1]);
    }

    public function test_commit_with_skip_strategy_leaves_the_existing_duplicate_untouched(): void
    {
        $this->seedCatalog();
        $existing = Student::factory()->create(['student_id_number' => '2024-0001', 'first_name' => 'Original']);

        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [],
            'duplicate' => [$this->row(['student_id_number' => '2024-0001', 'first_name' => 'Overwritten', '_row' => 2])],
            'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $this->actingAs($this->counselor())->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1,
        ])->assertOk();

        $this->assertSame('Original', $existing->fresh()->first_name);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_commit_with_update_strategy_overwrites_the_existing_duplicate(): void
    {
        $this->seedCatalog();
        $existing = Student::factory()->create(['student_id_number' => '2024-0001', 'first_name' => 'Original']);

        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [],
            'duplicate' => [$this->row(['student_id_number' => '2024-0001', 'first_name' => 'Updated', '_row' => 2])],
            'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $this->actingAs($this->counselor())->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'update', 'page' => 1,
        ])->assertOk();

        $this->assertSame('Updated', $existing->fresh()->first_name);
        $this->assertSame('Updated Dela Cruz', $existing->fresh()->user->name);
        $this->assertDatabaseCount('students', 1);
    }

    public function test_commit_is_idempotent_when_the_same_chunk_is_retried(): void
    {
        $this->seedCatalog();
        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [$this->row(['_row' => 2])],
            'duplicate' => [], 'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $payload = ['import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1];
        $counselor = $this->counselor();

        // A retried network call for the same chunk (e.g. a dropped response)
        // must update the now-existing row instead of erroring or duplicating it.
        $this->actingAs($counselor)->postJson(route('admin.students.import.commit'), $payload)->assertOk();
        $this->actingAs($counselor)->postJson(route('admin.students.import.commit'), $payload)->assertOk();

        $this->assertDatabaseCount('students', 1);
        $this->assertDatabaseCount('users', 2); // counselor + the one imported student
    }

    public function test_commit_paginates_across_chunks_of_fifty(): void
    {
        $this->seedCatalog();
        $rows = [];
        for ($i = 1; $i <= 60; $i++) {
            $rows[] = $this->row(['student_id_number' => sprintf('2024-%04d', $i), '_row' => $i + 1]);
        }

        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => $rows, 'duplicate' => [], 'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $counselor = $this->counselor();

        $this->actingAs($counselor)->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1,
        ])->assertOk()->assertJson(['current_page' => 1, 'total_pages' => 2, 'progress' => 50]);

        $this->assertDatabaseCount('students', 50);

        $this->actingAs($counselor)->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 2,
        ])->assertOk()->assertJson(['current_page' => 2, 'total_pages' => 2, 'progress' => 100]);

        $this->assertDatabaseCount('students', 60);
    }

    public function test_commit_rejects_an_unknown_or_expired_import_id(): void
    {
        $this->actingAs($this->counselor())->postJson(route('admin.students.import.commit'), [
            'import_id' => (string) Str::uuid(), 'duplicate_strategy' => 'skip', 'page' => 1,
        ])->assertStatus(400);
    }

    // ── Error report download ─────────────────────────────────────────────

    public function test_error_report_download_includes_the_error_reason_column(): void
    {
        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [], 'duplicate' => [],
            'invalid' => [$this->row(['gender' => 'Other', '_errors' => ['The selected gender is invalid.']])],
            'header' => self::HEADER,
        ], now()->addHours(2));

        $response = $this->actingAs($this->counselor())
            ->get(route('admin.students.import.errors', $importId))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('error_reason', $csv);
        $this->assertStringContainsString('The selected gender is invalid.', $csv);
    }

    public function test_error_report_redirects_when_the_import_id_is_unknown(): void
    {
        $this->actingAs($this->counselor())
            ->get(route('admin.students.import.errors', (string) Str::uuid()))
            ->assertRedirect(route('admin.students.index'));
    }

    // ── Activation codes download ────────────────────────────────────────

    public function test_activation_codes_download_lists_the_generated_codes(): void
    {
        $this->seedCatalog();
        $importId = (string) Str::uuid();
        Cache::put("import_{$importId}", [
            'valid' => [$this->row(['_row' => 2])],
            'duplicate' => [], 'invalid' => [], 'header' => self::HEADER,
        ], now()->addHours(2));

        $this->actingAs($this->counselor())->postJson(route('admin.students.import.commit'), [
            'import_id' => $importId, 'duplicate_strategy' => 'skip', 'page' => 1,
        ])->assertOk();

        $response = $this->actingAs($this->counselor())
            ->get(route('admin.students.import.codes', $importId))
            ->assertOk();

        $csv = $response->streamedContent();
        $this->assertStringContainsString('activation_code', $csv);
        $this->assertStringContainsString('2024-0001', $csv);
    }

    public function test_activation_codes_download_redirects_when_none_were_generated(): void
    {
        $this->actingAs($this->counselor())
            ->get(route('admin.students.import.codes', (string) Str::uuid()))
            ->assertRedirect(route('admin.students.index'));
    }

    // ── Template ──────────────────────────────────────────────────────────

    public function test_template_download_contains_the_expected_header(): void
    {
        $response = $this->actingAs($this->counselor())
            ->get(route('admin.students.import.template'))
            ->assertOk();

        $this->assertStringStartsWith(implode(',', self::HEADER), $response->streamedContent());
    }
}

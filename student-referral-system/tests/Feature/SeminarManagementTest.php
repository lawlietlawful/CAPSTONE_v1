<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use App\Models\SmsLog;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Seminars (BaseSeminarController + the counselor/admin seminar views):
 *
 *  - The calendar view was blank: an unclosed <div> nested it inside the
 *    hidden grid view.
 *  - Save Attendance re-stamped attended_at for everyone already attended,
 *    erasing QR check-in times and restarting the 30-day effectiveness clock.
 *  - Target Course was free text ("e.g. BSIT") matched exactly against the
 *    catalog's full names, so it matched nobody.
 *  - Validation errors in the seminar modals were never shown.
 *  - Past seminars stayed "Upcoming" without the scheduler, and were still
 *    recommended to teachers.
 *  - "SMS notifications sent." even when none were.
 *  - No way to take a student off a roster.
 *  - The counselor's edit page bounced back to itself and had no ML trigger.
 *  - Auto-assign ignored the target audience; cancelled seminars took
 *    assignments; "excused" couldn't be recorded.
 */
class SeminarManagementTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    /** [acting user, route prefix] for both sides that manage seminars. */
    public static function roles(): array
    {
        return ['counselor' => ['admin', 'counselor'], 'super admin' => ['super_admin', 'admin']];
    }

    private function seminar(array $overrides = []): Seminar
    {
        return Seminar::create($overrides + [
            'title' => 'Study Habits',
            'date' => now()->addDays(7)->toDateString(),
            'time' => '10:00:00',
            'venue' => 'AVR',
            'is_required' => false,
            'trigger_reason' => 'general',
            'status' => 'upcoming',
        ]);
    }

    private function payload(array $overrides = []): array
    {
        return $overrides + [
            'title' => 'Peer Support',
            'date' => now()->addDays(10)->toDateString(),
            'time' => '14:00',
            'venue' => 'Room 101',
            'trigger_reason' => 'general',
            'is_required' => '0',
            'status' => 'upcoming',
        ];
    }

    private function pivot(Seminar $seminar, Student $student)
    {
        return $seminar->students()->where('students.id', $student->id)->first()?->pivot;
    }

    // ── Calendar ────────────────────────────────────────────────────────

    #[DataProvider('roles')]
    public function test_the_calendar_is_not_nested_inside_the_hidden_grid_view(string $role, string $prefix): void
    {
        $this->seminar();
        $html = $this->actingAs(User::factory()->create(['role' => $role]))
            ->get(route("{$prefix}.seminars.index"))->assertOk()->getContent();

        // A real HTML5 parser, so the tree matches what the browser builds
        // (libxml's HTML4 parser repairs unbalanced markup differently).
        $dom = \Dom\HTMLDocument::createFromString($html, LIBXML_NOERROR);

        $this->assertNotNull($dom->querySelector('#calendar'));
        $this->assertNotNull($dom->querySelector('[x-show="viewMode === \'grid\'"]'));
        $this->assertNull($dom->querySelector('[x-show="viewMode === \'grid\'"] #calendar'));
    }

    public function test_the_calendar_and_qr_libraries_load_from_local_copies(): void
    {
        $seminar = $this->seminar();
        $counselor = $this->counselor();

        $this->actingAs($counselor)->get(route('counselor.seminars.index'))
            ->assertSee(asset('vendor/fullcalendar.global.min.js'), false)
            ->assertDontSee('cdn.jsdelivr.net/npm/fullcalendar', false);
        $this->actingAs($counselor)->get(route('counselor.seminars.show', $seminar))
            ->assertSee(asset('vendor/qrcode.min.js'), false)
            ->assertDontSee('cdnjs.cloudflare.com/ajax/libs/qrcodejs', false);

        $this->assertFileExists(public_path('vendor/fullcalendar.global.min.js'));
        $this->assertFileExists(public_path('vendor/qrcode.min.js'));
    }

    // ── Attendance ──────────────────────────────────────────────────────

    public function test_resaving_the_roster_keeps_an_existing_check_in_time(): void
    {
        $seminar = $this->seminar();
        [$checkedIn, $other] = Student::factory()->count(2)->create();
        $seminar->students()->attach($checkedIn->id, ['status' => 'attended', 'attended_at' => '2026-09-01 08:15:00']);
        $seminar->students()->attach($other->id, ['status' => 'enrolled']);

        // Only the other student's remark changes.
        $this->actingAs($this->counselor())->patch(route('counselor.seminars.attendance', $seminar), ['attendance' => [
            $checkedIn->id => ['status' => 'attended', 'remarks' => null],
            $other->id => ['status' => 'enrolled', 'remarks' => 'typo fix'],
        ]])->assertSessionHasNoErrors();

        $this->assertSame('2026-09-01 08:15:00', $this->pivot($seminar, $checkedIn)->attended_at->format('Y-m-d H:i:s'));
        $this->assertSame('typo fix', $this->pivot($seminar, $other)->remarks);
    }

    public function test_marking_attended_stamps_the_time_and_marking_otherwise_clears_it(): void
    {
        $seminar = $this->seminar();
        $student = Student::factory()->create();
        $seminar->students()->attach($student->id, ['status' => 'enrolled']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->patch(route('counselor.seminars.attendance', $seminar),
            ['attendance' => [$student->id => ['status' => 'attended']]]);
        $this->assertNotNull($this->pivot($seminar, $student)->attended_at);

        $this->actingAs($counselor)->patch(route('counselor.seminars.attendance', $seminar),
            ['attendance' => [$student->id => ['status' => 'missed']]]);
        $this->assertNull($this->pivot($seminar, $student)->attended_at);
    }

    public function test_excused_can_be_recorded(): void
    {
        $seminar = $this->seminar();
        $student = Student::factory()->create();
        $seminar->students()->attach($student->id, ['status' => 'enrolled']);

        $this->actingAs($this->counselor())->patch(route('counselor.seminars.attendance', $seminar),
            ['attendance' => [$student->id => ['status' => 'excused', 'remarks' => 'Medical']]])
            ->assertSessionHasNoErrors();

        $this->assertSame('excused', $this->pivot($seminar, $student)->status);
        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('value="excused" class="sr-only peer" checked', false);
    }

    public function test_attendance_for_someone_not_on_the_roster_changes_nothing(): void
    {
        $seminar = $this->seminar();
        $outsider = Student::factory()->create();

        $this->actingAs($this->counselor())->patch(route('counselor.seminars.attendance', $seminar),
            ['attendance' => [$outsider->id => ['status' => 'attended']]])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('student_seminars', ['student_id' => $outsider->id]);
    }

    // ── Validation feedback in the modals ───────────────────────────────

    public function test_a_failed_create_reopens_the_create_modal_with_its_errors(): void
    {
        $counselor = $this->counselor();

        $this->actingAs($counselor)->from(route('counselor.seminars.index'))
            ->post(route('counselor.seminars.store'), $this->payload(['max_participants' => 0, 'title' => 'Kept Title']));

        $html = $this->actingAs($counselor)->get(route('counselor.seminars.index'))->getContent();

        $this->assertStringContainsString('showCreateModal: true', $html);
        $this->assertSame(1, substr_count($html, 'data-seminar-form-errors'));
        $this->assertStringContainsString('Max capacity must be at least 1, or left blank for unlimited.', $html);
        $this->assertStringContainsString('value="Kept Title"', $html);
        $this->assertSame(0, Seminar::count());
    }

    public function test_a_failed_edit_shows_its_errors_only_in_that_seminars_modal(): void
    {
        $a = $this->seminar(['title' => 'Seminar A']);
        $this->seminar(['title' => 'Seminar B']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->from(route('counselor.seminars.index'))
            ->put(route('counselor.seminars.update', $a), $this->payload(['id' => $a->id, 'title' => '']));

        $html = $this->actingAs($counselor)->get(route('counselor.seminars.index'))->getContent();

        $this->assertStringContainsString('showCreateModal: false', $html);
        $this->assertSame(1, substr_count($html, 'showEditModal: true'));
        $this->assertSame(1, substr_count($html, 'data-seminar-form-errors'));
        // The block sits inside A's edit form, not B's or the create form.
        $errorsAt = strpos($html, 'data-seminar-form-errors');
        $aFormAt = strpos($html, 'action="' . route('counselor.seminars.update', $a) . '"');
        $this->assertGreaterThan($aFormAt, $errorsAt);
        $this->assertSame('Seminar A', $a->fresh()->title);
    }

    public function test_roster_errors_are_shown_on_the_seminar_page(): void
    {
        $seminar = $this->seminar();
        $counselor = $this->counselor();

        $this->actingAs($counselor)->from(route('counselor.seminars.show', $seminar))
            ->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [999999]]);

        $this->actingAs($counselor)->get(route('counselor.seminars.show', $seminar))
            ->assertSee('data-roster-errors', false)
            ->assertSee('showEditModal: false', false);
    }

    // ── Target audience ─────────────────────────────────────────────────

    public function test_target_course_must_be_a_catalog_course(): void
    {
        Course::create(['name' => 'Bachelor of Science in Information Technology', 'education_level' => 'College']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->post(route('counselor.seminars.store'), $this->payload(['target_course' => 'BSIT']))
            ->assertSessionHasErrors(['target_course' => 'Choose a target course from the catalog.']);

        $this->actingAs($counselor)->post(route('counselor.seminars.store'), $this->payload([
            'target_course' => 'Bachelor of Science in Information Technology', 'target_grade_level' => '2nd Year',
        ]))->assertSessionHasNoErrors();

        $this->assertSame('Bachelor of Science in Information Technology', Seminar::first()->target_course);
    }

    public function test_a_seminar_targeting_a_catalog_course_finds_its_students(): void
    {
        Course::create(['name' => 'Bachelor of Science in Nursing', 'education_level' => 'College']);
        $nurse = Student::factory()->create(['course' => 'Bachelor of Science in Nursing', 'grade_level' => '1st Year', 'status' => 'active']);
        $other = Student::factory()->create(['course' => 'Something Else', 'grade_level' => '1st Year', 'status' => 'active']);
        $seminar = $this->seminar(['target_course' => 'Bachelor of Science in Nursing']);

        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('name="student_ids[]" value="' . $nurse->id . '"', false)
            ->assertDontSee('name="student_ids[]" value="' . $other->id . '"', false);
    }

    public function test_an_older_off_catalog_target_is_kept_when_editing(): void
    {
        $seminar = $this->seminar(['target_course' => 'BSIT']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->get(route('counselor.seminars.show', $seminar))
            ->assertSee('<option value="BSIT" selected>BSIT (not in catalog)</option>', false);

        $this->actingAs($counselor)->put(route('counselor.seminars.update', $seminar), $this->payload(['target_course' => 'BSIT']))
            ->assertSessionHasNoErrors();
        $this->assertSame('BSIT', $seminar->fresh()->target_course);
    }

    public function test_the_target_year_must_belong_to_the_courses_education_level(): void
    {
        Course::create(['name' => 'Bachelor of Science in Nursing', 'education_level' => 'College']);
        Course::create(['name' => 'Basic Education', 'education_level' => 'Basic Education']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->post(route('counselor.seminars.store'), $this->payload([
            'target_course' => 'Bachelor of Science in Nursing', 'target_grade_level' => 'Grade 7',
        ]))->assertSessionHasErrors(['target_grade_level' => "Grade 7 isn't a level in Bachelor of Science in Nursing (College)."]);

        $this->actingAs($counselor)->post(route('counselor.seminars.store'), $this->payload([
            'target_course' => 'Basic Education', 'target_grade_level' => 'Grade 7',
        ]))->assertSessionHasNoErrors();

        // No course: any year/grade is fine.
        $this->actingAs($counselor)->post(route('counselor.seminars.store'), $this->payload(['target_grade_level' => '5th Year']))
            ->assertSessionHasNoErrors();
        $this->assertSame(2, Seminar::count());
    }

    public function test_capacity_cannot_drop_below_the_current_roster(): void
    {
        $seminar = $this->seminar(['max_participants' => 10]);
        $seminar->students()->attach(Student::factory()->count(3)->create()->pluck('id'), ['status' => 'enrolled']);

        $this->actingAs($this->counselor())->put(route('counselor.seminars.update', $seminar), $this->payload(['max_participants' => 2]))
            ->assertSessionHasErrors(['max_participants' => "3 students are already on the roster — max capacity can't be lower than that."]);

        $this->assertSame(10, $seminar->fresh()->max_participants);
    }

    // ── ML trigger reason ───────────────────────────────────────────────

    public function test_the_trigger_reason_can_be_changed_when_editing(): void
    {
        $seminar = $this->seminar(['trigger_reason' => 'general']);
        $counselor = $this->counselor();

        $this->actingAs($counselor)->get(route('counselor.seminars.show', $seminar))
            ->assertSee('name="trigger_reason"', false);

        $this->actingAs($counselor)->put(route('counselor.seminars.update', $seminar), $this->payload(['trigger_reason' => 'anti_bullying']))
            ->assertSessionHasNoErrors();
        $this->assertSame('anti_bullying', $seminar->fresh()->trigger_reason);

        $this->actingAs($counselor)->put(route('counselor.seminars.update', $seminar), $this->payload(['trigger_reason' => 'made_up']))
            ->assertSessionHasErrors(['trigger_reason']);
    }

    public function test_only_the_seminars_own_fields_are_saved(): void
    {
        $this->actingAs($this->counselor())->post(route('counselor.seminars.store'), $this->payload(['id' => 999, 'created_at' => '2000-01-01']))
            ->assertSessionHasNoErrors();

        $seminar = Seminar::first();
        $this->assertNotSame(999, $seminar->id);
        $this->assertNotSame('2000', $seminar->created_at->format('Y'));
    }

    // ── Stale statuses ──────────────────────────────────────────────────

    public function test_opening_the_seminars_page_completes_seminars_already_past(): void
    {
        $past = $this->seminar(['date' => now()->subDays(3)->toDateString(), 'status' => 'upcoming']);
        $future = $this->seminar(['status' => 'upcoming']);
        $cancelled = $this->seminar(['date' => now()->subDays(3)->toDateString(), 'status' => 'cancelled']);

        $this->actingAs($this->counselor())->get(route('counselor.seminars.index'))->assertOk();

        $this->assertSame('completed', $past->fresh()->status);
        $this->assertSame('upcoming', $future->fresh()->status);
        $this->assertSame('cancelled', $cancelled->fresh()->status);
    }

    public function test_past_seminars_are_not_recommended_to_teachers(): void
    {
        $this->seminar(['title' => 'Already Happened', 'date' => now()->subDay()->toDateString(), 'status' => 'upcoming']);
        $this->seminar(['title' => 'Still Ahead']);

        $titles = collect($this->actingAs(User::factory()->teacher()->create(), 'sanctum')
            ->getJson('/api/teacher/seminars/matching?tag=general')->assertOk()->json('seminars'))->pluck('title');

        $this->assertSame(['Still Ahead'], $titles->all());
    }

    public function test_upcoming_seminars_are_listed_before_past_ones(): void
    {
        $this->seminar(['title' => 'Long Ago', 'date' => now()->subDays(60)->toDateString(), 'status' => 'completed']);
        $this->seminar(['title' => 'Last Week', 'date' => now()->subDays(7)->toDateString(), 'status' => 'completed']);
        $this->seminar(['title' => 'Next Month', 'date' => now()->addDays(30)->toDateString()]);
        $this->seminar(['title' => 'Tomorrow', 'date' => now()->addDay()->toDateString()]);

        $html = $this->actingAs($this->counselor())->get(route('counselor.seminars.index'))->getContent();
        preg_match_all('/data-seminar-title>\s*<a [^>]+>(.+?)<\/a>/', $html, $m);

        $this->assertSame(['Tomorrow', 'Next Month', 'Last Week', 'Long Ago'], $m[1]);
    }

    // ── Assigning and SMS ───────────────────────────────────────────────

    public function test_an_optional_seminar_doesnt_claim_sms_were_sent(): void
    {
        $seminar = $this->seminar(['is_required' => false]);
        $student = Student::factory()->create(['parent_contact' => '09171234567', 'student_contact' => '09181234567']);

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$student->id]])
            ->assertSessionHas('success', '1 student assigned successfully.');

        $this->assertSame(0, SmsLog::count());
    }

    public function test_a_required_upcoming_seminar_reports_how_many_sms_went_out(): void
    {
        $seminar = $this->seminar(['is_required' => true]);
        $student = Student::factory()->create(['parent_contact' => '09171234567', 'student_contact' => '09181234567']);

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$student->id]])
            ->assertSessionHas('success', '1 student assigned successfully. SMS sent to 2 contacts.');

        $this->assertSame(2, SmsLog::count());
        $this->assertStringContainsString('at 10:00 AM', SmsLog::first()->message);
    }

    public function test_a_required_seminar_already_past_sends_no_sms(): void
    {
        $seminar = $this->seminar(['is_required' => true, 'date' => now()->subDays(2)->toDateString(), 'status' => 'completed']);
        $student = Student::factory()->create(['parent_contact' => '09171234567']);
        Student::factory()->create(['status' => 'active']);   // still assignable, so the form (and its note) shows

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$student->id]])
            ->assertSessionHas('success', '1 student assigned successfully.');

        $this->assertSame(0, SmsLog::count());
        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('no SMS will be sent', false);
    }

    public function test_a_cancelled_seminar_takes_no_assignments(): void
    {
        $seminar = $this->seminar(['status' => 'cancelled']);
        $student = Student::factory()->create();

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$student->id]])
            ->assertSessionHas('error', "This seminar is cancelled — students can't be assigned to it.");

        $this->assertNull($this->pivot($seminar, $student));
        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('data-assign-closed', false)
            ->assertDontSee('Add to Roster');
    }

    // ── Removing from the roster ────────────────────────────────────────

    #[DataProvider('roles')]
    public function test_a_student_can_be_removed_from_the_roster(string $role, string $prefix): void
    {
        $seminar = $this->seminar();
        $student = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz']);
        $seminar->students()->attach($student->id, ['status' => 'enrolled']);
        $user = User::factory()->create(['role' => $role]);

        // x-data: the roster has no Alpine component above it, and without
        // one the button's @click (which opens the confirm dialog) is dead.
        $this->actingAs($user)->get(route("{$prefix}.seminars.show", $seminar))
            ->assertSee('data-remove-student="' . $student->id . '" x-data @click="$dispatch(\'open-confirm-modal\'', false)
            ->assertSee('action="' . route("{$prefix}.seminars.students.remove", [$seminar, $student]) . '"', false);

        $this->actingAs($user)->delete(route("{$prefix}.seminars.students.remove", [$seminar, $student]))
            ->assertSessionHas('success', 'Ana Cruz was removed from the roster.');

        $this->assertNull($this->pivot($seminar, $student));
    }

    public function test_an_attended_student_cannot_be_removed(): void
    {
        $seminar = $this->seminar();
        $student = Student::factory()->create();
        $seminar->students()->attach($student->id, ['status' => 'attended', 'attended_at' => now()]);

        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertDontSee('data-remove-student="' . $student->id . '"', false);

        $this->actingAs($this->counselor())->delete(route('counselor.seminars.students.remove', [$seminar, $student]))
            ->assertSessionHas('error');

        $this->assertNotNull($this->pivot($seminar, $student));
    }

    public function test_removing_someone_not_on_the_roster_is_refused(): void
    {
        $seminar = $this->seminar();
        $student = Student::factory()->create();

        $this->actingAs($this->counselor())->delete(route('counselor.seminars.students.remove', [$seminar, $student]))
            ->assertSessionHas('error');
    }

    // ── Auto-assign ─────────────────────────────────────────────────────

    public function test_auto_assign_respects_the_target_course(): void
    {
        $seminar = $this->seminar(['date' => now()->addDays(4)->toDateString(), 'target_course' => 'Bachelor of Science in Nursing']);
        $nurse = Student::factory()->create(['course' => 'Bachelor of Science in Nursing', 'status' => 'active']);
        $other = Student::factory()->create(['course' => 'Bachelor of Science in Information Technology', 'status' => 'active']);

        foreach ([$nurse, $other] as $student) {
            RiskAssessment::create([
                'student_id' => $student->id, 'risk_score' => 60, 'risk_level' => 'moderate', 'assessed_at' => now(),
                'risk_factors' => ['recommended_seminar_tag' => 'general'],
            ]);
        }

        $this->artisan('seminars:auto-assign')->assertSuccessful();

        $this->assertNotNull($this->pivot($seminar, $nurse));
        $this->assertNull($this->pivot($seminar, $other));
    }

    // ── Attendance summaries ────────────────────────────────────────────

    private function seminarWithAttendance(): Seminar
    {
        $seminar = $this->seminar(['max_participants' => 10]);
        foreach (['attended', 'attended', 'missed', 'enrolled', 'excused'] as $status) {
            $seminar->students()->attach(Student::factory()->create()->id, [
                'status' => $status, 'attended_at' => $status === 'attended' ? now() : null,
            ]);
        }

        return $seminar;
    }

    public function test_the_seminar_page_summarises_attendance(): void
    {
        $seminar = $this->seminarWithAttendance();

        $html = $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))->getContent();

        foreach (['enrolled' => 1, 'attended' => 2, 'missed' => 1, 'excused' => 1] as $status => $count) {
            $this->assertMatchesRegularExpression('/data-attendance-count="' . $status . '">\s*<div[^>]*>' . $count . '<\/div>/', $html, $status);
        }
        // 2 attended of 3 taken (excused and pending don't count against it).
        $this->assertStringContainsString('67% attended', $html);
        $this->assertStringContainsString('5 slots left', $html);
    }

    public function test_the_seminars_list_shows_the_attendance_rate_and_per_card_counts(): void
    {
        $this->seminarWithAttendance();

        $this->actingAs($this->counselor())->get(route('counselor.seminars.index'))
            ->assertSee('67%')
            ->assertSee('3 taken')
            ->assertSee('2 attended')
            ->assertSee('1 missed');
    }

    public function test_the_assign_card_says_when_no_one_is_left_to_assign(): void
    {
        $seminar = $this->seminar();
        $student = Student::factory()->create(['status' => 'active']);
        $seminar->students()->attach($student->id, ['status' => 'enrolled']);

        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('data-assign-empty', false)
            ->assertSee('No one left to assign');
    }

    // ── Pages ───────────────────────────────────────────────────────────

    public function test_there_are_no_standalone_create_or_edit_pages(): void
    {
        foreach (['admin', 'counselor'] as $prefix) {
            $this->assertFalse(Route::has("{$prefix}.seminars.create"), "{$prefix}.seminars.create");
            $this->assertFalse(Route::has("{$prefix}.seminars.edit"), "{$prefix}.seminars.edit");
        }
    }

    public function test_the_delete_dialog_says_the_roster_goes_too(): void
    {
        $seminar = $this->seminar(['title' => "Parents' \"Night\""]);
        $seminar->students()->attach(Student::factory()->count(2)->create()->pluck('id'), ['status' => 'enrolled']);

        $this->actingAs($this->counselor())->get(route('counselor.seminars.index'))
            ->assertSee('Its roster of 2 students, with their attendance records, will be deleted too.', false)
            ->assertDontSee("Parents' \"Night\"", false);
    }

    public function test_the_qr_warns_when_its_link_only_works_on_this_computer(): void
    {
        $seminar = $this->seminar();

        $this->actingAs($this->counselor())->get('http://127.0.0.1:8000/counselor/seminars/' . $seminar->id)
            ->assertSee('data-qr-local-warning', false);
        $this->actingAs($this->counselor())->get('http://192.168.1.20:8000/counselor/seminars/' . $seminar->id)
            ->assertDontSee('data-qr-local-warning', false);
    }

    public function test_the_admin_and_counselor_seminar_views_stay_identical(): void
    {
        foreach (['index', 'show', 'print_roster'] as $view) {
            $counselor = str_replace(
                ["route('counselor.", "@extends('layouts.counselor')"],
                ["route('admin.", "@extends('layouts.admin')"],
                str_replace("\r\n", "\n", file_get_contents(resource_path("views/counselor/seminars/{$view}.blade.php")))
            );
            $admin = str_replace("\r\n", "\n", file_get_contents(resource_path("views/admin/seminars/{$view}.blade.php")));

            $this->assertSame($counselor, $admin, "admin/seminars/{$view} has drifted from counselor/seminars/{$view}");
        }
    }
}

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

    // ── Assign enforces the audience (whatever page it comes from) ──────

    public function test_assigning_a_student_outside_the_target_audience_is_refused(): void
    {
        $seminar = $this->seminar(['target_course' => 'Bachelor of Science in Nursing']);
        $outsider = Student::factory()->create(['first_name' => 'Ivan', 'last_name' => 'Tech', 'course' => 'BSIT']);

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$outsider->id]])
            ->assertSessionHas('error', "Ivan Tech can't be assigned — this seminar is for Bachelor of Science in Nursing (active students only).");

        $this->assertNull($this->pivot($seminar, $outsider));
    }

    public function test_an_inactive_student_cannot_be_assigned(): void
    {
        $seminar = $this->seminar();
        $graduate = Student::factory()->create(['status' => 'graduated']);

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$graduate->id]])
            ->assertSessionHas('error');

        $this->assertNull($this->pivot($seminar, $graduate));
    }

    // ── Referral page recommendations ───────────────────────────────────

    private function referralWithTag(Student $student, string $tag = 'general')
    {
        $assessment = RiskAssessment::create([
            'student_id' => $student->id, 'risk_score' => 60, 'risk_level' => 'moderate', 'assessed_at' => now(),
            'risk_factors' => ['recommended_seminar_tag' => $tag],
        ]);

        return \App\Models\Referral::factory()->create(['student_id' => $student->id, 'risk_assessment_id' => $assessment->id]);
    }

    public function test_recommendations_only_list_seminars_the_student_can_join(): void
    {
        $student = Student::factory()->create(['course' => 'Bachelor of Science in Nursing', 'status' => 'active']);
        $referral = $this->referralWithTag($student);
        $open = $this->seminar(['title' => 'Open To All']);
        $nursing = $this->seminar(['title' => 'Nursing Only', 'target_course' => 'Bachelor of Science in Nursing']);
        $itOnly = $this->seminar(['title' => 'IT Only', 'target_course' => 'BSIT']);
        $full = $this->seminar(['title' => 'Already Full', 'max_participants' => 1]);
        $full->students()->attach(Student::factory()->create()->id, ['status' => 'enrolled']);

        $html = $this->actingAs($this->counselor())->get(route('counselor.referrals.show', $referral))->assertOk()->getContent();

        $this->assertStringContainsString('data-recommended-seminar="' . $open->id . '"', $html);
        $this->assertStringContainsString('data-recommended-seminar="' . $nursing->id . '"', $html);
        $this->assertStringNotContainsString('data-recommended-seminar="' . $itOnly->id . '"', $html);
        // A full seminar is listed as Full, without an Assign button.
        $fullBlock = substr($html, strpos($html, 'data-recommended-seminar="' . $full->id . '"'), 1500);
        $this->assertStringContainsString('no slots left', $fullBlock);
        $this->assertStringNotContainsString(route('counselor.seminars.assign', $full), $fullBlock);
    }

    // ── A full seminar ──────────────────────────────────────────────────

    public function test_a_full_seminar_shows_a_full_state_instead_of_the_checklist(): void
    {
        $seminar = $this->seminar(['max_participants' => 1]);
        $seminar->students()->attach(Student::factory()->create()->id, ['status' => 'enrolled']);
        Student::factory()->create(['status' => 'active']);   // still eligible

        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('data-assign-full', false)
            ->assertSee('Raise capacity')
            ->assertDontSee('data-assign-list', false);
    }

    // ── Cancel / reschedule notifications ───────────────────────────────

    private function rosterOfTwo(Seminar $seminar): array
    {
        $pending = Student::factory()->create(['first_name' => 'Pia', 'parent_contact' => '09171111111', 'student_contact' => '09182222222']);
        $done = Student::factory()->create(['parent_contact' => '09173333333']);
        $seminar->students()->attach($pending->id, ['status' => 'enrolled']);
        $seminar->students()->attach($done->id, ['status' => 'excused']);

        return [$pending, $done];
    }

    public function test_cancelling_texts_the_students_still_pending(): void
    {
        $seminar = $this->seminar(['title' => 'Peer Support']);
        [$pending] = $this->rosterOfTwo($seminar);

        $this->actingAs($this->counselor())->put(route('counselor.seminars.update', $seminar), $this->payload([
            'title' => 'Peer Support', 'date' => $seminar->date->toDateString(), 'time' => '10:00', 'venue' => 'AVR',
            'status' => 'cancelled', 'notify_students' => '1',
        ]))->assertSessionHas('success', 'Seminar updated successfully. SMS sent to 2 contacts about the cancellation.');

        // Only the pending student (parent + student), not the excused one.
        $this->assertSame(2, SmsLog::count());
        $this->assertEqualsCanonicalizing([$pending->id], SmsLog::pluck('student_id')->unique()->all());
        $this->assertStringContainsString("'Peer Support' has been CANCELLED", SmsLog::where('recipient_type', 'student')->first()->message);
    }

    public function test_rescheduling_texts_the_new_date_time_and_venue(): void
    {
        $seminar = $this->seminar(['title' => 'Peer Support']);
        $this->rosterOfTwo($seminar);
        $newDate = now()->addDays(9);

        $this->actingAs($this->counselor())->put(route('counselor.seminars.update', $seminar), $this->payload([
            'title' => 'Peer Support', 'date' => $newDate->toDateString(), 'time' => '15:30', 'venue' => 'Gym',
            'notify_students' => '1',
        ]))->assertSessionHas('success', 'Seminar updated successfully. SMS sent to 2 contacts about the change.');

        $this->assertStringContainsString(
            'has been moved to ' . $newDate->format('M d, Y') . ' at 03:30 PM (Gym)',
            SmsLog::where('recipient_type', 'student')->first()->message
        );
    }

    public function test_no_texts_when_unticked_or_when_nothing_students_need_changed(): void
    {
        $seminar = $this->seminar(['title' => 'Peer Support', 'venue' => 'AVR']);
        $this->rosterOfTwo($seminar);
        $counselor = $this->counselor();

        // Unticked: cancelled, but no SMS.
        $this->actingAs($counselor)->put(route('counselor.seminars.update', $seminar), $this->payload([
            'date' => $seminar->date->toDateString(), 'time' => '10:00', 'venue' => 'AVR', 'status' => 'cancelled',
        ]))->assertSessionHas('success', 'Seminar updated successfully.');

        // Ticked, but only the description changed.
        $other = $this->seminar(['title' => 'Other', 'venue' => 'AVR']);
        $this->rosterOfTwo($other);
        $this->actingAs($counselor)->put(route('counselor.seminars.update', $other), $this->payload([
            'title' => 'Other', 'date' => $other->date->toDateString(), 'time' => '10:00', 'venue' => 'AVR',
            'description' => 'Typo fixed', 'notify_students' => '1',
        ]))->assertSessionHas('success', 'Seminar updated successfully.');

        $this->assertSame(0, SmsLog::count());
    }

    public function test_the_edit_form_offers_to_text_pending_students(): void
    {
        $seminar = $this->seminar();
        $this->rosterOfTwo($seminar);
        $empty = $this->seminar(['title' => 'Nobody Yet']);

        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))
            ->assertSee('data-notify-students', false)
            ->assertSee('Text the 1 pending student');
        $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $empty))
            ->assertDontSee('data-notify-students', false);
    }

    // ── Clashes ─────────────────────────────────────────────────────────

    public function test_a_venue_clash_is_flagged_but_not_blocked(): void
    {
        $date = now()->addDays(6)->toDateString();
        $this->seminar(['title' => 'Morning Talk', 'date' => $date, 'time' => '09:00:00', 'venue' => 'AVR']);

        $this->actingAs($this->counselor())->post(route('counselor.seminars.store'), $this->payload([
            'date' => $date, 'time' => '10:00', 'venue' => 'avr',
        ]))->assertSessionHas('success', 'Seminar created successfully. Heads-up: "Morning Talk" is also in AVR at 09:00 AM that day.');

        // Far enough apart, or a different venue: no note.
        $this->actingAs($this->counselor())->post(route('counselor.seminars.store'), $this->payload([
            'date' => $date, 'time' => '13:00', 'venue' => 'AVR',
        ]))->assertSessionHas('success', 'Seminar created successfully.');

        $this->assertSame(3, Seminar::count());
    }

    public function test_double_booked_students_are_tagged_and_flagged(): void
    {
        $date = now()->addDays(6)->toDateString();
        $other = $this->seminar(['title' => 'Morning Talk', 'date' => $date, 'time' => '09:00:00', 'venue' => 'AVR']);
        $seminar = $this->seminar(['title' => 'Overlap', 'date' => $date, 'time' => '10:00:00', 'venue' => 'Gym']);
        $busy = Student::factory()->create(['first_name' => 'Bea', 'last_name' => 'Busy', 'status' => 'active']);
        $free = Student::factory()->create(['status' => 'active']);
        $other->students()->attach($busy->id, ['status' => 'enrolled']);

        $html = $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))->getContent();
        $this->assertSame(1, substr_count($html, 'data-booked-elsewhere'));

        $this->actingAs($this->counselor())->post(route('counselor.seminars.assign', $seminar), ['student_ids' => [$busy->id, $free->id]])
            ->assertSessionHas('success', '2 students assigned successfully. Heads-up: already booked for another seminar at the same time — Bea Busy ("Morning Talk").');
    }

    // ── Student Portal API ──────────────────────────────────────────────

    public function test_the_student_portal_only_lists_seminars_still_ahead_as_required(): void
    {
        $student = Student::factory()->create();
        $ahead = $this->seminar(['title' => 'Ahead']);
        $cancelled = $this->seminar(['title' => 'Called Off', 'status' => 'cancelled']);
        $past = $this->seminar(['title' => 'Not Recorded', 'date' => now()->subDays(3)->toDateString(), 'status' => 'completed']);
        $missed = $this->seminar(['title' => 'Missed One', 'date' => now()->subDays(10)->toDateString(), 'status' => 'completed']);
        foreach ([$ahead, $cancelled, $past] as $s) {
            $s->students()->attach($student->id, ['status' => 'enrolled']);
        }
        $missed->students()->attach($student->id, ['status' => 'missed']);

        $json = $this->actingAs($student->user, 'sanctum')->getJson('/api/student/seminars')->assertOk()->json();

        $this->assertSame(['Ahead'], collect($json['required'])->pluck('title')->all());
        $this->assertEqualsCanonicalizing(['Called Off', 'Not Recorded', 'Missed One'], collect($json['completed'])->pluck('title')->all());
        $this->assertSame('cancelled', collect($json['completed'])->firstWhere('title', 'Called Off')['session_status']);
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

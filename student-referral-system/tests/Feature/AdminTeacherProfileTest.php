<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Message;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Admin\TeacherController@show. The profile used to show only behavioral
 * reports — never the referrals a teacher raised, even though Engagement
 * weighs the two equally — and the right column (one table) sat far shorter
 * than the left (three cards), leaving ~320px of empty space under it.
 * Referrals are now a first-class section, and both columns are flex columns
 * that end on a common edge.
 */
class AdminTeacherProfileTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->counselor()->create();
    }

    private function page(User $teacher): string
    {
        return $this->actingAs($this->admin())
            ->get(route('admin.teachers.show', $teacher->id))
            ->assertOk()
            ->getContent();
    }

    /** [left column html, right column html]. */
    private function columns(string $html): array
    {
        $left = strpos($html, 'lg:col-span-1 flex flex-col gap-6');
        $right = strpos($html, 'lg:col-span-2 flex flex-col gap-6');
        $this->assertNotFalse($left);
        $this->assertNotFalse($right);
        $this->assertLessThan($right, $left);

        return [substr($html, $left, $right - $left), substr($html, $right)];
    }

    public function test_the_profile_lists_referrals_the_teacher_raised(): void
    {
        $teacher = User::factory()->teacher()->create();
        $student = Student::factory()->create(['first_name' => 'Angelo', 'last_name' => 'Fernandez']);
        $referral = Referral::factory()->create([
            'student_id' => $student->id, 'referred_by' => $teacher->id,
            'referral_type' => 'Misconduct', 'priority' => 'high', 'status' => 'pending',
        ]);

        $html = $this->page($teacher);

        $this->assertStringContainsString('Recent Referrals', $html);
        $this->assertStringContainsString('Fernandez, Angelo', $html);
        $this->assertStringContainsString('Referrals Filed', $html);
        $this->assertStringContainsString(route('counselor.referrals.show', $referral->id), $html, 'links to the referral');
    }

    public function test_the_referrals_filed_stat_counts_the_whole_history_not_just_the_recent_ten(): void
    {
        $teacher = User::factory()->teacher()->create();
        Referral::factory()->count(12)->create(['referred_by' => $teacher->id]);

        $html = $this->page($teacher);

        // The table caps at 10 recent rows; the stat must still say 12.
        $this->assertStringContainsString('12', $html);
    }

    public function test_in_progress_referral_status_reads_as_two_words(): void
    {
        $teacher = User::factory()->teacher()->create();
        Referral::factory()->create(['referred_by' => $teacher->id, 'status' => 'in_progress']);

        $html = $this->page($teacher);

        $this->assertStringContainsString('In Progress', $html);
        $this->assertStringNotContainsString('In_progress', $html);
    }

    public function test_a_teacher_with_neither_reports_nor_referrals_shows_both_empty_states(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->page($teacher);

        $this->assertStringContainsString('No behavioral reports filed.', $html);
        $this->assertStringContainsString('No referrals filed.', $html);
    }

    public function test_only_this_teachers_own_referrals_and_reports_appear(): void
    {
        $teacher = User::factory()->teacher()->create();
        $other = User::factory()->teacher()->create();
        $mine = Student::factory()->create(['first_name' => 'Alice', 'last_name' => 'Ownstudent']);
        $theirs = Student::factory()->create(['first_name' => 'Bob', 'last_name' => 'Otherstudent']);
        Referral::factory()->create(['referred_by' => $teacher->id, 'student_id' => $mine->id]);
        Referral::factory()->create(['referred_by' => $other->id, 'student_id' => $theirs->id]);
        BehavioralReport::factory()->create(['reported_by' => $teacher->id, 'student_id' => $mine->id]);
        BehavioralReport::factory()->create(['reported_by' => $other->id, 'student_id' => $theirs->id]);

        $html = $this->page($teacher);

        $this->assertStringContainsString('Ownstudent, Alice', $html);
        $this->assertStringNotContainsString('Otherstudent, Bob', $html);
    }

    public function test_both_columns_are_flex_columns_and_the_shorter_ones_last_card_grows_to_the_common_edge(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->page($teacher);

        [$left] = $this->columns($html);

        $this->assertStringNotContainsString('lg:col-span-1 space-y-6', $html);
        $this->assertStringNotContainsString('lg:col-span-2 space-y-6', $html);
        // Course Assignments is the left column's last card — it's the one
        // that grows when the right column (two tables) ends up taller.
        // The heading itself (not the "<!-- Course Assignments -->" comment
        // just above it, which would otherwise be the first match).
        $headingAt = strpos($left, 'ti-clipboard-list');
        $this->assertNotFalse($headingAt);
        $wrapperAt = strpos($left, 'flex-1 flex flex-col', max(0, $headingAt - 600));
        $this->assertNotFalse($wrapperAt);
        $this->assertLessThan(
            $headingAt,
            $wrapperAt,
            'the growable wrapper opens just before the Course Assignments card',
        );
    }

    public function test_send_notice_opens_the_shared_messages_popup_instead_of_navigating_away(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->page($teacher);

        // It dispatches the shared popup (component/messages-modal.blade.php)
        // with this teacher preselected, rather than linking to a full page.
        $this->assertStringContainsString(
            "\$dispatch('open-messages', { to: {$teacher->id} })",
            $html,
        );
        $this->assertStringNotContainsString(
            'href="' . route('counselor.messages.index') . '"',
            $html,
            'no longer a plain link away from the profile',
        );
    }

    public function test_the_header_is_a_compact_avatar_beside_the_name_not_a_tall_centered_stack(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => 'Roberto Villanueva']);

        $html = $this->page($teacher);

        $this->assertMatchesRegularExpression('#flex items-center gap-4">\s*<div class="w-14 h-14 rounded-full#', $html);
        $this->assertStringContainsString('Roberto Villanueva', $html);
        $this->assertStringNotContainsString('w-20 h-20 rounded-full bg-blue-100', $html, 'the tall centered avatar is gone');
    }

    // ── Recent Notices ──────────────────────────────────────────────────

    public function test_recent_notices_lists_threads_either_direction(): void
    {
        // The card always names the OTHER party relative to the teacher
        // whose profile this is — showing the teacher's own name back at
        // them would be redundant on their own page.
        $admin = User::factory()->counselor()->create(['name' => 'Edago Counselor']);
        $teacher = User::factory()->teacher()->create(['name' => 'Carlos Ramos']);
        Message::create([
            'sender_id' => $admin->id, 'receiver_id' => $teacher->id,
            'subject' => 'Reminder', 'content' => 'Please see me about a student.',
        ]);
        Message::create([
            'sender_id' => $teacher->id, 'receiver_id' => $admin->id, 'content' => 'Will do, thank you.',
        ]);

        $html = $this->actingAs($admin)->get(route('admin.teachers.show', $teacher->id))->getContent();

        $this->assertStringContainsString('Recent Notices', $html);
        $this->assertStringContainsString('Reminder', $html);
        $this->assertStringContainsString('Will do, thank you.', $html);
        $this->assertStringContainsString('From Edago Counselor', $html, 'the notice the admin sent the teacher');
        $this->assertStringContainsString('To Edago Counselor', $html, 'the reply the teacher sent back');
    }

    public function test_recent_notices_only_shows_top_level_threads_not_replies(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->teacher()->create();
        $thread = Message::create([
            'sender_id' => $admin->id, 'receiver_id' => $teacher->id, 'content' => 'Original notice.',
        ]);
        Message::create([
            'sender_id' => $teacher->id, 'receiver_id' => $admin->id,
            'parent_id' => $thread->id, 'content' => 'A reply, not a new thread.',
        ]);

        $page = $this->actingAs($admin)->get(route('admin.teachers.show', $teacher->id));

        $this->assertCount(1, $page->viewData('recentNotices'));
    }

    public function test_recent_notices_shows_at_most_five_newest(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->teacher()->create();
        foreach (range(1, 7) as $i) {
            Message::create(['sender_id' => $admin->id, 'receiver_id' => $teacher->id, 'content' => "Notice {$i}"]);
        }

        $notices = $this->actingAs($admin)
            ->get(route('admin.teachers.show', $teacher->id))
            ->viewData('recentNotices');

        $this->assertCount(5, $notices);
        $this->assertSame('Notice 7', $notices->first()->content);
    }

    public function test_a_notice_between_two_other_counselors_is_shown_but_not_clickable(): void
    {
        $viewer = $this->admin();
        $otherCounselor = User::factory()->counselor()->create();
        $teacher = User::factory()->teacher()->create();
        Message::create(['sender_id' => $otherCounselor->id, 'receiver_id' => $teacher->id, 'content' => 'Not yours to open.']);

        $html = $this->actingAs($viewer)->get(route('admin.teachers.show', $teacher->id))->getContent();

        $this->assertStringContainsString('Not yours to open.', $html);
        // "Send Notice" elsewhere on the page also dispatches open-messages
        // (with a `to:` payload) — check specifically for no `thread:`
        // dispatch, which is what a clickable notice row would add.
        $this->assertStringNotContainsString('{ thread:', $html);
    }

    public function test_a_teacher_with_no_notices_shows_an_empty_state(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->page($teacher);

        $this->assertStringContainsString('No notices sent yet.', $html);
    }

    public function test_the_course_assignments_modal_offers_a_duplicate_row_button(): void
    {
        $teacher = User::factory()->teacher()->create();

        $html = $this->page($teacher);

        $this->assertStringContainsString('duplicateAssignment(index)', $html);
        $this->assertStringContainsString('Duplicate this row', $html);
        // Copies the row in place (splice at index + 1), not appended to the end.
        $this->assertStringContainsString(
            'duplicateAssignment(i) { this.assignments.splice(i + 1, 0, { ...this.assignments[i] }); }',
            $html,
        );
    }
}

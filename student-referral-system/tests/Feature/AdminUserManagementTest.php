<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * User Management (Admin\UserController + admin/users/index). The access
 * boundary itself is covered by AdminUserAccessTest; this covers the page:
 *
 *  - Deleting a user is ON DELETE CASCADE for every report, referral and
 *    intervention they filed, and the confirmation never said so.
 *  - A rejected edit was invisible: the page reopened "Add New User" for
 *    every validation failure (and the script doing it threw a JS error),
 *    so the edit's own errors were never shown.
 *  - A counselor was offered Super Admin / Admin roles the server always
 *    rejects for them.
 *  - Long course names overflowed the Assigned To column into Joined.
 *  - No search or filter at all.
 */
class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function superAdmin(): User
    {
        return User::factory()->create(['role' => 'super_admin']);
    }

    /** The HTML from one row's edit form up to the next one (or the end). */
    private function editSection(string $html, int $userId): string
    {
        $start = strpos($html, 'data-edit-form="' . $userId . '"');
        $this->assertNotFalse($start, "edit form for user {$userId} not rendered");
        $next = strpos($html, 'data-edit-form="', $start + 10);

        return substr($html, $start, $next === false ? null : $next - $start);
    }

    // ── Delete is only for accounts with nothing on file ─────────────────
    // Every report, referral and intervention a user filed is ON DELETE
    // CASCADE. Accounts with records are deactivated instead (see
    // AccountDeactivationTest); Delete is only offered — and only allowed —
    // for an account with nothing to lose.

    public function test_a_teacher_with_records_is_offered_deactivate_not_delete(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => 'Carlos Ramos']);
        BehavioralReport::factory()->count(3)->create(['reported_by' => $teacher->id]);
        Referral::factory()->count(2)->create(['referred_by' => $teacher->id]);

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();

        $this->assertStringNotContainsString('data-delete-user="' . $teacher->id . '"', $html);
        $this->assertStringContainsString('data-deactivate-user="' . $teacher->id . '"', $html);
        // The disabled bin explains why, with the exact counts.
        $this->assertStringContainsString('Has 3 behavioral reports, 2 referrals on file', $html);
    }

    public function test_a_user_with_nothing_on_file_can_still_be_deleted(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => 'Created By Mistake']);

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();
        $this->assertStringContainsString('data-delete-user="' . $teacher->id . '"', $html);
        $this->assertStringContainsString('They have nothing on file', $html);

        $this->actingAs($this->counselor())->delete(route('admin.users.destroy', $teacher->id))
            ->assertRedirect(route('admin.users.index'));
        $this->assertDatabaseMissing('users', ['id' => $teacher->id]);
    }

    public function test_deleting_a_user_with_records_is_refused_and_nothing_is_erased(): void
    {
        // The server enforces it too — the hidden button isn't the only guard.
        $teacher = User::factory()->teacher()->create();
        $report = BehavioralReport::factory()->create(['reported_by' => $teacher->id]);
        $referral = Referral::factory()->create(['referred_by' => $teacher->id]);

        $this->actingAs($this->counselor())->delete(route('admin.users.destroy', $teacher->id))
            ->assertRedirect(route('admin.users.index'))
            ->assertSessionHas('error', fn ($m) => str_contains($m, 'Deactivate the account instead'));

        $this->assertDatabaseHas('users', ['id' => $teacher->id]);
        $this->assertDatabaseHas('behavioral_reports', ['id' => $report->id]);
        $this->assertDatabaseHas('referrals', ['id' => $referral->id]);
    }

    public function test_a_counselor_with_assigned_referrals_cannot_be_deleted_either(): void
    {
        // Not erased (counselor_id is SET NULL) but every open case of theirs
        // would silently become unassigned — so it counts as "on file" too.
        $superAdmin = $this->superAdmin();
        $counselor = $this->counselor();
        $referral = Referral::factory()->create(['counselor_id' => $counselor->id]);

        $this->actingAs($superAdmin)->delete(route('admin.users.destroy', $counselor->id))
            ->assertSessionHas('error', fn ($m) => str_contains($m, '1 assigned referral'));

        $this->assertSame($counselor->id, $referral->fresh()->counselor_id);
    }

    // ── Validation failures reopen the right modal ───────────────────────

    public function test_a_failed_edit_reopens_that_rows_edit_modal_with_its_error(): void
    {
        $counselor = $this->counselor();
        $teacher = User::factory()->teacher()->create(['name' => 'Carlos Ramos']);
        User::factory()->teacher()->create(['email' => 'taken@example.com']);

        $html = $this->actingAs($counselor)
            ->from(route('admin.users.index'))
            ->followingRedirects()
            ->put(route('admin.users.update', $teacher->id), [
                '_form' => 'edit-' . $teacher->id,
                'name' => 'Carlos Ramos',
                'email' => 'taken@example.com',
                'role' => 'teacher',
            ])
            ->getContent();

        $section = $this->editSection($html, $teacher->id);
        $this->assertStringContainsString('data-edit-errors', $section);
        $this->assertStringContainsString('has already been taken', $section);
        $this->assertStringContainsString('value="taken@example.com"', $section, 'keeps what was typed');

        $this->assertSame(1, substr_count($html, 'showEditModal: true'), 'only the failed row reopens');
        $this->assertStringContainsString('showAddUser: false', $html, 'the Add modal stays closed');
        $this->assertStringNotContainsString('__x.$data', $html, 'the old script that threw is gone');
    }

    public function test_a_failed_edit_does_not_leak_its_input_into_other_rows(): void
    {
        $counselor = $this->counselor();
        $failing = User::factory()->teacher()->create(['name' => 'Failing Teacher']);
        $other = User::factory()->teacher()->create(['name' => 'Other Teacher', 'email' => 'other@example.com']);

        $html = $this->actingAs($counselor)
            ->from(route('admin.users.index'))
            ->followingRedirects()
            ->put(route('admin.users.update', $failing->id), [
                '_form' => 'edit-' . $failing->id,
                'name' => 'Typed Into Failing',
                'email' => 'other@example.com',
                'role' => 'teacher',
            ])
            ->getContent();

        $otherSection = $this->editSection($html, $other->id);
        $this->assertStringContainsString('value="Other Teacher"', $otherSection);
        $this->assertStringNotContainsString('Typed Into Failing', $otherSection);
        $this->assertStringNotContainsString('data-edit-errors', $otherSection);
    }

    public function test_a_failed_add_reopens_the_add_modal_not_an_edit_modal(): void
    {
        $counselor = $this->counselor();
        User::factory()->teacher()->create(['email' => 'taken@example.com']);

        $html = $this->actingAs($counselor)
            ->from(route('admin.users.index'))
            ->followingRedirects()
            ->post(route('admin.users.store'), [
                '_form' => 'add',
                'name' => 'New Teacher',
                'email' => 'taken@example.com',
                'role' => 'teacher',
                'username' => 'T-2026-999',
            ])
            ->getContent();

        $this->assertStringContainsString('showAddUser: true', $html);
        $this->assertStringContainsString('data-add-errors', $html);
        $this->assertStringContainsString('value="New Teacher"', $html);
        $this->assertSame(0, substr_count($html, 'showEditModal: true'));
    }

    // ── Role options match what the server accepts ───────────────────────

    public function test_a_counselor_is_not_offered_roles_they_cannot_assign(): void
    {
        User::factory()->teacher()->create();

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();

        $this->assertStringNotContainsString('<option value="super_admin">', $html);
        $this->assertStringNotContainsString('<option value="admin">', $html);
        $this->assertStringContainsString('data-role-locked', $html);
        $this->assertStringContainsString('<input type="hidden" name="role" value="teacher">', $html);
    }

    public function test_a_super_admin_is_offered_every_role(): void
    {
        User::factory()->teacher()->create();

        $html = $this->actingAs($this->superAdmin())->get(route('admin.users.index'))->getContent();

        $this->assertStringContainsString('<option value="super_admin">', $html);
        $this->assertStringContainsString('<option value="admin">', $html);
        $this->assertStringNotContainsString('data-role-locked', $html);
    }

    public function test_a_counselor_can_still_create_a_teacher_through_the_locked_role(): void
    {
        $this->actingAs($this->counselor())->post(route('admin.users.store'), [
            '_form' => 'add',
            'name' => 'New Teacher',
            'email' => 'newteacher@example.com',
            'role' => 'teacher',
            'username' => 'T-2026-900',
        ])->assertRedirect(route('admin.users.index'))
          ->assertSessionHas('activation_code');

        $this->assertDatabaseHas('users', ['email' => 'newteacher@example.com', 'role' => 'teacher']);
    }

    // ── Assigned To column ───────────────────────────────────────────────

    public function test_assignment_chips_truncate_inside_their_column_with_the_full_text_on_hover(): void
    {
        $teacher = User::factory()->teacher()->create();
        TeacherAssignment::create([
            'teacher_id' => $teacher->id,
            'course' => 'Bachelor of Science in Hospitality Management',
            'grade_level' => '1st Year',
            'section' => 'Block 1',
        ]);

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();

        $this->assertStringContainsString('max-w-full truncate', $html);
        $this->assertStringContainsString('title="Bachelor of Science in Hospitality Management · 1st Year · Block 1"', $html);
        $this->assertStringNotContainsString('<table class="w-full text-left text-sm whitespace-nowrap">', $html, 'no longer nowrap end to end');
    }

    public function test_the_modals_use_the_shared_course_assignment_rows(): void
    {
        User::factory()->teacher()->create();

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();

        $this->assertStringContainsString('data-assignment-row', $html);
        $this->assertStringContainsString('Duplicate this row', $html);
        $this->assertStringContainsString('duplicateAssignment(i)', $html, 'the function the shared rows call is defined');
    }

    public function test_a_teachers_name_links_to_their_teacher_profile(): void
    {
        $teacher = User::factory()->teacher()->create(['name' => 'Carlos Ramos']);

        $html = $this->actingAs($this->counselor())->get(route('admin.users.index'))->getContent();

        $this->assertStringContainsString(route('admin.teachers.show', $teacher->id), $html);
    }

    // ── Search & filter ──────────────────────────────────────────────────

    public function test_search_matches_name_email_or_employee_id(): void
    {
        $counselor = $this->counselor();
        User::factory()->teacher()->create(['name' => 'Carlos Ramos', 'email' => 'c@example.com', 'username' => 'T-1']);
        User::factory()->teacher()->create(['name' => 'Josefina Ramos', 'email' => 'j@example.com', 'username' => 'T-2']);
        User::factory()->teacher()->create(['name' => 'Manuel Aquino', 'email' => 'm@example.com', 'username' => 'EMP-777']);

        $names = fn ($q) => $this->actingAs($counselor)->get(route('admin.users.index', ['search' => $q]))
            ->viewData('users')->pluck('name')->sort()->values()->all();

        $this->assertSame(['Carlos Ramos', 'Josefina Ramos'], $names('Ramos'));
        $this->assertSame(['Josefina Ramos'], $names('j@example'));
        $this->assertSame(['Manuel Aquino'], $names('EMP-777'));
    }

    public function test_a_super_admin_can_filter_by_role(): void
    {
        $super = $this->superAdmin();
        $this->counselor();
        User::factory()->teacher()->count(2)->create();

        $roles = $this->actingAs($super)->get(route('admin.users.index', ['role' => 'admin']))
            ->viewData('users')->pluck('role')->unique()->values()->all();

        $this->assertSame(['admin'], $roles);
    }

    public function test_a_counselor_cannot_use_the_role_filter_to_see_beyond_teachers(): void
    {
        $counselor = $this->counselor();
        $this->superAdmin();
        User::factory()->teacher()->create();

        $roles = $this->actingAs($counselor)->get(route('admin.users.index', ['role' => 'super_admin']))
            ->viewData('users')->pluck('role')->unique()->values()->all();

        $this->assertSame(['teacher'], $roles);
    }

    public function test_search_survives_pagination(): void
    {
        $counselor = $this->counselor();
        User::factory()->teacher()->count(12)->sequence(fn ($s) => ['name' => 'Ramos ' . $s->index])->create();

        $users = $this->actingAs($counselor)->get(route('admin.users.index', ['search' => 'Ramos']))
            ->viewData('users');

        $this->assertStringContainsString('search=Ramos', $users->nextPageUrl());
    }
}

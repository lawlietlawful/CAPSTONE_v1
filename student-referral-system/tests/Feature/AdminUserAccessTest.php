<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The User Management access boundary: a counselor may manage teacher
 * accounts only, never another counselor's or the super admin's. show()
 * is covered here specifically because it was the one sibling action
 * (alongside edit/update/resetPassword/destroy) that didn't get this guard
 * when the others did — a counselor could view (though not edit or delete)
 * any user's profile, including a super admin's, by ID.
 */
class AdminUserAccessTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    // ── show() ───────────────────────────────────────────────────────────

    public function test_counselor_cannot_view_another_counselors_profile(): void
    {
        $counselor = $this->counselor();
        $otherCounselor = $this->counselor();

        $this->actingAs($counselor)
            ->get(route('admin.users.show', $otherCounselor->id))
            ->assertForbidden();
    }

    public function test_counselor_cannot_view_the_super_admins_profile(): void
    {
        $counselor = $this->counselor();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($counselor)
            ->get(route('admin.users.show', $superAdmin->id))
            ->assertForbidden();
    }

    public function test_counselor_can_view_a_teachers_profile(): void
    {
        $counselor = $this->counselor();
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($counselor)
            ->get(route('admin.users.show', $teacher->id))
            ->assertOk();
    }

    public function test_super_admin_can_view_any_profile(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $counselor = $this->counselor();

        $this->actingAs($superAdmin)
            ->get(route('admin.users.show', $counselor->id))
            ->assertOk();
    }

    // ── index() scoping (regression guard for the same boundary) ─────────

    public function test_counselor_user_list_only_contains_teachers(): void
    {
        $counselor = $this->counselor();
        $this->counselor(); // another counselor — must not appear
        User::factory()->create(['role' => 'super_admin']); // must not appear
        $teacher = User::factory()->teacher()->create();

        $response = $this->actingAs($counselor)->get(route('admin.users.index'));

        $response->assertOk();
        $response->assertViewHas('users', function ($users) use ($teacher) {
            return $users->total() === 1 && $users->first()->id === $teacher->id;
        });
    }

    // ── edit/update/destroy (already-guarded siblings, kept honest) ───────

    public function test_counselor_cannot_edit_another_counselor(): void
    {
        $counselor = $this->counselor();
        $otherCounselor = $this->counselor();

        $this->actingAs($counselor)
            ->get(route('admin.users.edit', $otherCounselor->id))
            ->assertForbidden();
    }

    public function test_counselor_cannot_delete_another_counselor(): void
    {
        $counselor = $this->counselor();
        $otherCounselor = $this->counselor();

        $this->actingAs($counselor)
            ->delete(route('admin.users.destroy', $otherCounselor->id))
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $otherCounselor->id]);
    }
}

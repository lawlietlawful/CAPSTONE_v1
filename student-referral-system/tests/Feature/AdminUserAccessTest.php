<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The User Management access boundary: a counselor may manage teacher
 * accounts only, never another counselor's or the super admin's.
 *
 * The standalone show/edit/create pages used to be covered here (show() was
 * once the one action missing this guard). They're gone now — adding and
 * editing happen in modals on the index — so this pins that they stay gone,
 * and covers the boundary on the actions that remain, including the new
 * deactivate/reactivate.
 */
class AdminUserAccessTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    // ── The standalone pages are gone ────────────────────────────────────

    public function test_the_standalone_show_edit_and_create_pages_no_longer_exist(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $teacher = User::factory()->teacher()->create();

        $this->assertFalse(\Route::has('admin.users.show'));
        $this->assertFalse(\Route::has('admin.users.edit'));
        $this->assertFalse(\Route::has('admin.users.create'));

        // GET /admin/users/{id} only matches update/destroy now → 405, not a page.
        $this->assertContains($this->actingAs($superAdmin)->get("/admin/users/{$teacher->id}")->status(), [404, 405]);
        $this->assertSame(404, $this->actingAs($superAdmin)->get("/admin/users/{$teacher->id}/edit")->status());
        $this->assertContains($this->actingAs($superAdmin)->get('/admin/users/create')->status(), [404, 405]);
    }

    // ── index() scoping ──────────────────────────────────────────────────

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

    // ── update / destroy / deactivate / reactivate ───────────────────────

    public function test_counselor_cannot_update_another_counselor(): void
    {
        $counselor = $this->counselor();
        $otherCounselor = $this->counselor();

        $this->actingAs($counselor)
            ->put(route('admin.users.update', $otherCounselor->id), [
                'name' => 'Hijacked', 'email' => $otherCounselor->email, 'role' => 'teacher',
            ])
            ->assertForbidden();

        $this->assertNotSame('Hijacked', $otherCounselor->fresh()->name);
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

    public function test_counselor_cannot_deactivate_or_reactivate_another_counselor(): void
    {
        $counselor = $this->counselor();
        $otherCounselor = $this->counselor();

        $this->actingAs($counselor)->post(route('admin.users.deactivate', $otherCounselor->id))->assertForbidden();
        $this->assertFalse($otherCounselor->fresh()->isDeactivated());

        $otherCounselor->update(['deactivated_at' => now()]);
        $this->actingAs($counselor)->post(route('admin.users.reactivate', $otherCounselor->id))->assertForbidden();
        $this->assertTrue($otherCounselor->fresh()->isDeactivated());
    }

    public function test_counselor_cannot_deactivate_the_super_admin(): void
    {
        $counselor = $this->counselor();
        $superAdmin = User::factory()->create(['role' => 'super_admin']);

        $this->actingAs($counselor)->post(route('admin.users.deactivate', $superAdmin->id))->assertForbidden();

        $this->assertFalse($superAdmin->fresh()->isDeactivated());
    }

    public function test_super_admin_can_deactivate_a_counselor(): void
    {
        $superAdmin = User::factory()->create(['role' => 'super_admin']);
        $counselor = $this->counselor();

        $this->actingAs($superAdmin)->post(route('admin.users.deactivate', $counselor->id))->assertRedirect();

        $this->assertTrue($counselor->fresh()->isDeactivated());
    }
}

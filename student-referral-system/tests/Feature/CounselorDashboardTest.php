<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counselor dashboard's access gate and its "Pending Referrals" links.
 * Those links were pointed at admin.referrals.index (unscoped — every
 * referral in the system) instead of counselor.referrals.index (scoped to
 * "mine or unclaimed" via scopeMineOrUnclaimed()), so a counselor clicking
 * through from their own "Action Required" widget landed on everyone's
 * referrals instead of their own queue.
 */
class CounselorDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_non_counselor_cannot_load_the_dashboard(): void
    {
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->get(route('counselor.dashboard'))
            ->assertForbidden();
    }

    public function test_counselor_can_load_the_dashboard(): void
    {
        $counselor = User::factory()->counselor()->create();

        $this->actingAs($counselor)->get(route('counselor.dashboard'))
            ->assertOk();
    }

    public function test_pending_referrals_links_point_to_the_scoped_counselor_view(): void
    {
        $counselor = User::factory()->counselor()->create();

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertOk();
        $response->assertSee(route('counselor.referrals.index'), false);
        $response->assertDontSee(route('admin.referrals.index'), false);
    }
}

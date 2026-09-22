<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The counselor sidebar's "Behavioral Reports" link (and the dashboard's
 * "Reports Today" quick-link) pointed at admin.behavioral-reports.index
 * instead of the counselor's own route — a copy-paste mistake that silently
 * routed every real click to a completely different, unmaintained page while
 * counselor.behavioral-reports.* (with all its fixes) sat unused. Every other
 * counselor-specific resource (referrals, interventions) correctly links to
 * its own counselor.* route; this locks that convention in for reports too.
 */
class CounselorNavigationTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_sidebar_behavioral_reports_link_points_to_the_counselor_route(): void
    {
        $counselor = $this->counselor();

        $response = $this->actingAs($counselor)->get(route('counselor.dashboard'));

        $response->assertSee(route('counselor.behavioral-reports.index'), false);
        $response->assertDontSee(route('admin.behavioral-reports.index'), false);
    }
}

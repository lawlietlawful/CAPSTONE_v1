<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Mirrors the Counselor-side pagination fix — the Admin index was still
 * paginating at 20, out of step with Counselor's (and every other index
 * page's) 10-per-page convention.
 */
class AdminBehavioralReportPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_paginates_at_ten_per_page(): void
    {
        $admin = $this->admin();
        BehavioralReport::factory()->count(12)->create();

        $page = $this->actingAs($admin)->get(route('admin.behavioral-reports.index'));

        $reports = $page->viewData('reports');
        $this->assertSame(10, $reports->count());
        $this->assertSame(12, $reports->total());
    }
}

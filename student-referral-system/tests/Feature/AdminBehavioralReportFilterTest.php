<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * index() and export() filtered on a column named reported_by_id, but the
 * behavioral_reports table's actual column (per its migration and the
 * reportedBy() relation) is reported_by — using the "Reported By" teacher
 * filter threw a QueryException ("column not found") instead of filtering.
 * The counselor-side controller never had this bug; only Admin's did.
 */
class AdminBehavioralReportFilterTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_filtering_by_reported_by_id_does_not_throw_and_filters_correctly(): void
    {
        $admin = $this->admin();
        $teacherA = User::factory()->teacher()->create();
        $teacherB = User::factory()->teacher()->create();
        BehavioralReport::factory()->create(['reported_by' => $teacherA->id]);
        BehavioralReport::factory()->create(['reported_by' => $teacherB->id]);

        $response = $this->actingAs($admin)->get(route('admin.behavioral-reports.index', ['reported_by_id' => $teacherA->id]));

        $response->assertOk();
        $this->assertSame(1, $response->viewData('reports')->total());
    }

    public function test_export_filtered_by_reported_by_id_does_not_throw(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->teacher()->create();
        BehavioralReport::factory()->create(['reported_by' => $teacher->id]);

        $response = $this->actingAs($admin)->get(route('admin.behavioral-reports.export', ['reported_by_id' => $teacher->id]));

        $response->assertOk();
    }
}

<?php

namespace Tests\Feature;

use App\Http\Controllers\AbstractBehavioralReportController;
use App\Http\Controllers\Admin\BehavioralReportController as AdminReports;
use App\Http\Controllers\Counselor\BehavioralReportController as CounselorReports;
use App\Models\BehavioralReport;
use App\Models\Intervention;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use ReflectionClass;
use Tests\TestCase;

/**
 * The Admin and Counselor pages used to be separate copies of the same code,
 * which is how filters, exports and searches drifted apart. These tests pin
 * the shared behaviour so that can't happen again.
 */
class AdminCounselorParityTest extends TestCase
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

    // ── Behavioral reports: one implementation ───────────────────────────

    public function test_both_report_controllers_share_one_implementation(): void
    {
        foreach ([AdminReports::class, CounselorReports::class] as $class) {
            $this->assertTrue(is_subclass_of($class, AbstractBehavioralReportController::class), $class);

            $own = collect((new ReflectionClass($class))->getMethods())
                ->filter(fn ($m) => $m->class === $class)
                ->map->getName()
                ->all();

            $this->assertSame(['area'], $own, "{$class} must only say which view set it uses");
        }
    }

    public function test_admin_and_counselor_report_lists_return_the_same_reports_for_every_filter(): void
    {
        $teacher = User::factory()->teacher()->create();
        $maria = Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $pedro = Student::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Reyes']);
        BehavioralReport::factory()->create(['student_id' => $maria->id, 'reported_by' => $teacher->id, 'severity' => 'High', 'status' => 'pending', 'incident_type' => 'Disciplinary Incident', 'incident_date' => '2026-09-01']);
        BehavioralReport::factory()->create(['student_id' => $maria->id, 'severity' => 'Low', 'status' => 'reviewed', 'incident_type' => 'Truancy', 'incident_date' => '2026-09-20']);
        BehavioralReport::factory()->create(['student_id' => $pedro->id, 'severity' => 'High', 'status' => 'resolved', 'incident_type' => 'Truancy', 'incident_date' => '2026-08-01']);

        $filters = [
            [], ['severity' => 'High'], ['status' => 'reviewed'], ['incident_type' => 'Truancy'],
            ['search' => 'Maria Santos'], ['search' => 'reyes'], ['reported_by_id' => $teacher->id],
            ['date_from' => '2026-09-01'], ['date_to' => '2026-08-31'], ['severity' => 'High', 'search' => 'Santos, Maria'],
        ];

        foreach ($filters as $filter) {
            $admin = $this->actingAs($this->superAdmin())->get(route('admin.behavioral-reports.index', $filter))->viewData('reports')->pluck('id')->all();
            $counselor = $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.index', $filter))->viewData('reports')->pluck('id')->all();

            $this->assertSame($admin, $counselor, 'filter ' . json_encode($filter));
        }
    }

    public function test_admin_and_counselor_report_exports_are_identical(): void
    {
        $student = Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        BehavioralReport::factory()->create(['student_id' => $student->id, 'description' => 'He said "no", then left']);

        $admin = $this->actingAs($this->superAdmin())->get(route('admin.behavioral-reports.export', ['search' => 'Maria Santos']))->streamedContent();
        $counselor = $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.export', ['search' => 'Maria Santos']))->streamedContent();

        $this->assertSame($admin, $counselor);
        $this->assertStringContainsString('"He said ""no"", then left"', $admin);
    }

    public function test_the_report_print_page_works_for_both_areas(): void
    {
        $report = BehavioralReport::factory()->create();

        $this->actingAs($this->superAdmin())->get(route('admin.behavioral-reports.print', $report->id))->assertOk();
        $this->actingAs($this->counselor())->get(route('counselor.behavioral-reports.print', $report->id))->assertOk();
    }

    // ── Referrals: one filter ────────────────────────────────────────────

    public function test_referral_lists_find_a_full_name_in_either_order_on_both_pages(): void
    {
        $c = $this->counselor();
        $maria = Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Referral::factory()->create(['student_id' => $maria->id, 'counselor_id' => $c->id]);
        Referral::factory()->create(['student_id' => Student::factory()->create(['first_name' => 'Pedro', 'last_name' => 'Reyes'])->id, 'counselor_id' => $c->id]);

        foreach (['Maria Santos', 'Santos Maria', 'Santos, Maria', 'santos'] as $term) {
            $admin = $this->actingAs($this->superAdmin())->get(route('admin.referrals.index', ['search' => $term]))->viewData('referrals')->total();
            $counselor = $this->actingAs($c)->get(route('counselor.referrals.index', ['search' => $term]))->viewData('referrals')->total();

            $this->assertSame(1, $admin, "admin '{$term}'");
            $this->assertSame(1, $counselor, "counselor '{$term}'");
        }
    }

    public function test_admin_and_counselor_referral_lists_agree_on_status_priority_and_search(): void
    {
        $c = $this->counselor();
        $maria = Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        Referral::factory()->create(['student_id' => $maria->id, 'counselor_id' => $c->id, 'status' => 'pending', 'priority' => 'high']);
        Referral::factory()->create(['student_id' => $maria->id, 'counselor_id' => $c->id, 'status' => 'resolved', 'priority' => 'low']);
        Referral::factory()->create(['counselor_id' => $c->id, 'status' => 'pending', 'priority' => 'high']);

        foreach ([['status' => 'pending'], ['priority' => 'high'], ['search' => 'Maria Santos'], ['status' => 'pending', 'priority' => 'high', 'search' => 'Santos']] as $filter) {
            $admin = $this->actingAs($this->superAdmin())->get(route('admin.referrals.index', $filter))->viewData('referrals')->pluck('id')->sort()->values()->all();
            $counselor = $this->actingAs($c)->get(route('counselor.referrals.index', $filter))->viewData('referrals')->pluck('id')->sort()->values()->all();

            $this->assertSame($admin, $counselor, 'filter ' . json_encode($filter));
        }
    }

    public function test_the_counselor_referral_list_keeps_its_own_scope_on_top_of_the_shared_filter(): void
    {
        $me = $this->counselor();
        $other = $this->counselor();
        $mine = Referral::factory()->create(['counselor_id' => $me->id, 'status' => 'pending']);
        Referral::factory()->create(['counselor_id' => $other->id, 'status' => 'pending']);

        $ids = $this->actingAs($me)->get(route('counselor.referrals.index', ['status' => 'pending']))->viewData('referrals')->pluck('id')->all();

        $this->assertSame([$mine->id], $ids, "another counselor's referral never appears");
    }

    public function test_the_shared_referral_filter_ignores_blank_and_unknown_values(): void
    {
        Referral::factory()->count(3)->create();

        $count = Referral::filtered(['status' => '', 'priority' => null, 'date_range' => 'nonsense', 'bogus' => 'x', 'assignment' => 'mine'], null)->count();

        $this->assertSame(3, $count, "assignment=mine without a viewer id changes nothing");
    }

    public function test_the_shared_referral_filter_handles_last_month_on_the_31st(): void
    {
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
        Referral::factory()->create(['created_at' => '2026-09-15 09:00:00']);
        Referral::factory()->create(['created_at' => '2026-10-20 09:00:00']);

        $this->assertSame(1, Referral::filtered(['date_range' => 'last_month'])->count());
    }

    public function test_the_admin_referral_date_range_and_counselor_filters_still_work_through_the_shared_scope(): void
    {
        $admin = $this->superAdmin();
        $c = $this->counselor();
        Referral::factory()->create(['counselor_id' => $c->id, 'created_at' => now()]);
        Referral::factory()->create(['counselor_id' => $c->id, 'created_at' => now()->subMonths(3)]);
        Referral::factory()->create(['counselor_id' => null, 'created_at' => now()]);

        $this->assertSame(2, $this->actingAs($admin)->get(route('admin.referrals.index', ['date_range' => 'this_month']))->viewData('referrals')->total());
        $this->assertSame(2, $this->actingAs($admin)->get(route('admin.referrals.index', ['counselor_id' => $c->id]))->viewData('referrals')->total());
        $this->assertSame(1, $this->actingAs($admin)->get(route('admin.referrals.index', ['counselor_id' => $c->id, 'date_range' => 'this_month']))->viewData('referrals')->total());
    }

    // ── Interventions ────────────────────────────────────────────────────

    public function test_the_intervention_list_finds_a_full_name(): void
    {
        $c = $this->counselor();
        $maria = Student::factory()->create(['first_name' => 'Maria', 'last_name' => 'Santos']);
        $r = Referral::factory()->create(['student_id' => $maria->id]);
        Intervention::factory()->create(['referral_id' => $r->id, 'counselor_id' => $c->id]);
        Intervention::factory()->create(['counselor_id' => $c->id]);

        foreach (['Maria Santos', 'Santos, Maria'] as $term) {
            $this->assertSame(1, $this->actingAs($c)->get(route('counselor.interventions.index', ['search' => $term]))->viewData('interventions')->total(), $term);
        }
    }
}

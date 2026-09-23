<?php

namespace Tests\Feature;

use App\Models\BehavioralReport;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * "Export should match what's on screen." Each of these exports used to
 * carry its own shorter copy of its index page's filters, so a filtered
 * export silently contained rows the screen wasn't showing. Index and
 * export now share one filteredQuery() per controller.
 */
class ExportFilterParityTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->counselor()->create();
    }

    private function twoStudentsWithReports(): void
    {
        $zelda = Student::factory()->create(['first_name' => 'Zeldaxyz']);
        $other = Student::factory()->create(['first_name' => 'Qwertyu']);
        BehavioralReport::factory()->create(['student_id' => $zelda->id]);
        BehavioralReport::factory()->create(['student_id' => $other->id]);
    }

    public function test_counselor_behavioral_report_export_respects_the_search_filter(): void
    {
        $this->twoStudentsWithReports();

        $csv = $this->actingAs($this->counselor())
            ->get(route('counselor.behavioral-reports.export', ['search' => 'Zeldaxyz']))
            ->streamedContent();

        $this->assertStringContainsString('Zeldaxyz', $csv);
        $this->assertStringNotContainsString('Qwertyu', $csv);
    }

    public function test_admin_behavioral_report_export_respects_the_search_filter(): void
    {
        $this->twoStudentsWithReports();

        $csv = $this->actingAs($this->counselor())
            ->get(route('admin.behavioral-reports.export', ['search' => 'Zeldaxyz']))
            ->streamedContent();

        $this->assertStringContainsString('Zeldaxyz', $csv);
        $this->assertStringNotContainsString('Qwertyu', $csv);
    }

    public function test_admin_behavioral_report_export_is_valid_csv_with_quotes_and_commas(): void
    {
        $description = 'He said "no", then left, quietly';
        BehavioralReport::factory()->create(['description' => $description]);

        $csv = $this->actingAs($this->counselor())
            ->get(route('admin.behavioral-reports.export'))
            ->streamedContent();

        $rows = array_map('str_getcsv', array_values(array_filter(explode("\n", trim($csv)))));

        $this->assertCount(9, $rows[1]);
        $this->assertSame($description, $rows[1][8], 'the description must survive the export unaltered');
    }

    public function test_admin_referral_export_respects_the_counselor_filter(): void
    {
        $c1 = User::factory()->counselor()->create();
        $c2 = User::factory()->counselor()->create();
        $mine = Student::factory()->create(['first_name' => 'Aaaonly']);
        $theirs = Student::factory()->create(['first_name' => 'Bbbother']);
        Referral::factory()->create(['student_id' => $mine->id, 'counselor_id' => $c1->id]);
        Referral::factory()->create(['student_id' => $theirs->id, 'counselor_id' => $c2->id]);

        $csv = $this->actingAs($c1)
            ->get(route('admin.referrals.export', ['counselor_id' => $c1->id]))
            ->streamedContent();

        $this->assertStringContainsString('Aaaonly', $csv);
        $this->assertStringNotContainsString('Bbbother', $csv);
    }

    public function test_admin_referral_export_respects_the_date_range_filter(): void
    {
        $old = Student::factory()->create(['first_name' => 'Oldreferral']);
        $new = Student::factory()->create(['first_name' => 'Newreferral']);
        Referral::factory()->create(['student_id' => $old->id, 'created_at' => now()->subMonths(3)]);
        Referral::factory()->create(['student_id' => $new->id, 'created_at' => now()]);

        $csv = $this->actingAs($this->counselor())
            ->get(route('admin.referrals.export', ['date_range' => 'this_month']))
            ->streamedContent();

        $this->assertStringContainsString('Newreferral', $csv);
        $this->assertStringNotContainsString('Oldreferral', $csv);
    }

    public function test_admin_referrals_last_month_filter_is_correct_on_the_31st(): void
    {
        // subMonth() on Oct 31 overflows to Oct 1 (September has 30 days),
        // so "last month" used to search October instead of September.
        $this->travelTo(now()->setDate(2026, 10, 31)->setTime(10, 0));
        Referral::factory()->create(['created_at' => '2026-09-15 09:00:00']);

        $index = $this->actingAs($this->counselor())
            ->get(route('admin.referrals.index', ['date_range' => 'last_month']));
        $this->assertSame(1, $index->viewData('referrals')->total());
    }
}

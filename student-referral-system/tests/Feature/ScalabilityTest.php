<?php

namespace Tests\Feature;

use App\Models\Course;
use App\Models\RiskAssessment;
use App\Models\Seminar;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Found by load-testing a 6,000-student copy of the data:
 *
 *  - Manage Seminar rendered every eligible student (an 8.9 MB page) and
 *    Submit New Referral put every student in its picker (1.5 MB). Both now
 *    ship a first page and search on the server.
 *  - "Latest assessment" ids were fetched into PHP and sent back as one
 *    placeholder per student — slower, and a hard error past 65,535.
 *  - The dashboard watchlist loaded every high-risk student to show three.
 */
class ScalabilityTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function seminar(array $overrides = []): Seminar
    {
        return Seminar::create($overrides + [
            'title' => 'Study Habits', 'date' => now()->addDays(7)->toDateString(), 'time' => '10:00:00',
            'venue' => 'AVR', 'is_required' => false, 'trigger_reason' => 'general', 'status' => 'upcoming',
        ]);
    }

    // ── Seminar: first page with the page, search on the server ─────────

    public function test_the_seminar_page_ships_only_the_first_page_of_eligible_students(): void
    {
        Student::factory()->count(60)->create(['status' => 'active']);
        $seminar = $this->seminar();

        $html = $this->actingAs($this->counselor())->get(route('counselor.seminars.show', $seminar))->assertOk()->getContent();

        $this->assertStringContainsString('data-eligible-total="60"', $html);
        $this->assertStringContainsString('60 eligible students', $html);
        // The embedded results hold 50 students, not 60.
        preg_match("/results: JSON\\.parse\\('(.+?)'\\)/", $html, $m);
        $this->assertCount(50, json_decode(json_decode('"' . $m[1] . '"'), true));
    }

    public function test_the_eligible_search_finds_by_name_id_and_course_abbreviation(): void
    {
        Course::create(['name' => 'Bachelor of Science in Nursing', 'education_level' => 'College']);
        $ana = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'student_id_number' => '2026-7777', 'course' => 'Bachelor of Science in Nursing', 'status' => 'active']);
        $ben = Student::factory()->create(['first_name' => 'Ben', 'last_name' => 'Reyes', 'course' => 'BSIT', 'status' => 'active']);
        $seminar = $this->seminar();
        $counselor = $this->counselor();

        $find = fn (string $q) => collect($this->actingAs($counselor)
            ->getJson(route('counselor.seminars.eligible', [$seminar, 'q' => $q]))->assertOk()->json('students'))->pluck('id')->all();

        $this->assertSame([(string) $ana->id], $find('cruz ana'));
        $this->assertSame([(string) $ana->id], $find('7777'));
        $this->assertSame([(string) $ana->id], $find('BSN'));      // abbreviation of the full course name
        $this->assertSame([(string) $ben->id], $find('reyes'));
        // Each word must match: course abbreviation + year, or first + last name.
        $this->assertSame([(string) $ana->id], $find('BSN 1st'));
        $this->assertSame([], $find('BSN 3rd'));
        $this->assertSame([(string) $ben->id], $find('ben reyes'));
    }

    public function test_the_eligible_search_leaves_out_the_roster_inactive_and_out_of_audience_students(): void
    {
        $seminar = $this->seminar(['target_course' => 'BSIT']);
        $eligible = Student::factory()->create(['course' => 'BSIT', 'status' => 'active']);
        $onRoster = Student::factory()->create(['course' => 'BSIT', 'status' => 'active']);
        Student::factory()->create(['course' => 'BSIT', 'status' => 'graduated']);
        Student::factory()->create(['course' => 'BSN', 'status' => 'active']);
        $seminar->students()->attach($onRoster->id, ['status' => 'enrolled']);

        $json = $this->actingAs($this->counselor())->getJson(route('counselor.seminars.eligible', $seminar))->json();

        $this->assertSame(1, $json['total']);
        $this->assertSame([(string) $eligible->id], collect($json['students'])->pluck('id')->all());
    }

    public function test_the_eligible_search_returns_a_page_and_the_full_total(): void
    {
        Student::factory()->count(55)->create(['status' => 'active']);

        $json = $this->actingAs($this->counselor())->getJson(route('counselor.seminars.eligible', $this->seminar()))->json();

        $this->assertSame(55, $json['total']);
        $this->assertCount(50, $json['students']);
    }

    // ── Referral form picker ────────────────────────────────────────────

    public function test_the_student_lookup_searches_active_students_only(): void
    {
        $ana = Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Cruz', 'status' => 'active']);
        Student::factory()->create(['first_name' => 'Ana', 'last_name' => 'Gone', 'status' => 'graduated']);

        $json = $this->actingAs($this->counselor())->getJson(route('admin.students.lookup', ['q' => 'ana']))->assertOk()->json();

        $this->assertSame(1, $json['total']);
        $this->assertSame((string) $ana->id, $json['students'][0]['id']);
        $this->assertSame('Cruz, Ana', $json['students'][0]['label']);
    }

    public function test_the_referral_form_ships_twenty_students_but_keeps_a_preselected_one(): void
    {
        Student::factory()->count(25)->create(['last_name' => 'Aaa', 'status' => 'active']);
        $late = Student::factory()->create(['last_name' => 'Zzz', 'first_name' => 'Last', 'status' => 'active']);

        $html = $this->actingAs($this->counselor())->get(route('counselor.referrals.create', ['student_id' => $late->id]))->getContent();

        preg_match("/students: JSON\\.parse\\('(.+?)'\\)/", $html, $m);
        $students = collect(json_decode(json_decode('"' . $m[1] . '"'), true));
        $this->assertCount(21, $students);                                  // first 20 + the preselected one
        $this->assertTrue($students->pluck('id')->contains((string) $late->id));
        $this->assertStringContainsString('total: 26', $html);
    }

    public function test_teachers_and_students_cannot_use_the_lookups(): void
    {
        $seminar = $this->seminar();
        $teacher = User::factory()->teacher()->create();

        $this->actingAs($teacher)->getJson(route('admin.students.lookup'))->assertForbidden();
        $this->actingAs($teacher)->getJson(route('counselor.seminars.eligible', $seminar))->assertForbidden();
    }

    // ── Latest assessments ──────────────────────────────────────────────

    public function test_latest_ids_is_a_subquery_not_a_list(): void
    {
        $student = Student::factory()->create();
        RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 90, 'risk_level' => 'high', 'assessed_at' => now()->subDay()]);
        $latest = RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 20, 'risk_level' => 'low', 'assessed_at' => now()]);

        $ids = RiskAssessment::latestIds();

        $this->assertInstanceOf(Builder::class, $ids);
        $this->assertSame([$latest->id], RiskAssessment::whereIn('id', $ids)->pluck('id')->all());
        // No placeholders: the student ids aren't in the SQL's bindings.
        $this->assertStringContainsString('select MAX(id)', RiskAssessment::whereIn('id', $ids)->toSql());
    }

    // ── Dashboard watchlist ─────────────────────────────────────────────

    public function test_the_watchlist_is_sorted_and_paged_in_the_database(): void
    {
        $counselor = $this->counselor();
        $scores = [];
        foreach ([95, 72, 88, 91, 70] as $score) {
            $s = Student::factory()->create(['status' => 'active']);
            RiskAssessment::create(['student_id' => $s->id, 'risk_score' => $score, 'risk_level' => 'high', 'assessed_at' => now()]);
            $scores[$s->id] = $score;
        }

        \DB::enableQueryLog();
        $html = $this->actingAs($counselor)->get(route('counselor.dashboard'))->assertOk()->getContent();
        $log = collect(\DB::getQueryLog());

        // Only one page (3) of assessments is fetched for the watchlist.
        $this->assertTrue($log->contains(fn ($q) => str_contains($q['query'], 'order by `risk_score` desc') && str_contains($q['query'], 'limit 3')));
        // Highest scores first.
        $names = Student::whereIn('id', collect($scores)->sortDesc()->keys()->take(3))->get()->sortByDesc(fn ($s) => $scores[$s->id]);
        $positions = $names->map(fn ($s) => strpos($html, e($s->first_name . ' ' . $s->last_name)))->values();
        $this->assertFalse($positions->contains(false), 'each of the top three is on the watchlist');
        $this->assertSame($positions->sort()->values()->all(), $positions->all());
    }
}

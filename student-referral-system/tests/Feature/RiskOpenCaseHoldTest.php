<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use App\Services\ReferralService;
use App\Services\RiskAssessmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Only a student's LATEST assessment is ever shown, so a mild new incident
 * used to replace a serious open case: the score dropped, the recommended
 * seminar changed and the trend arrow turned green while a knife-threat
 * referral was still unresolved. While a referral is open its assessment
 * now holds the student's score up; resolving or cancelling it releases it.
 */
class RiskOpenCaseHoldTest extends TestCase
{
    use RefreshDatabase;

    /** Successive /predict calls answer with these [level, score, tag] triples, in order. */
    private function predictSequence(array $answers): void
    {
        $sequence = Http::sequence();
        foreach ($answers as [$level, $score, $tag]) {
            $sequence->push(['risk_level' => $level, 'risk_score' => $score, 'recommended_seminar_tag' => $tag], 200);
        }

        Http::fake(['*/predict' => $sequence, '*' => Http::response([], 200)]);
    }

    private function file(Student $student, string $reason = 'Some incident.'): Referral
    {
        return app(ReferralService::class)->create(User::factory()->teacher()->create(), [
            'student_id' => $student->id, 'referral_type' => 'Misconduct', 'reason' => $reason,
        ]);
    }

    private function latest(Student $student): RiskAssessment
    {
        return RiskAssessment::where('student_id', $student->id)->latest('id')->first();
    }

    public function test_a_mild_new_referral_does_not_lower_the_score_of_a_serious_open_case(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying'], ['low', 30, 'orientation']]);
        $student = Student::factory()->create();

        $serious = $this->file($student, 'Threatened a classmate with a knife.');
        $mild = $this->file($student, 'Late to class once.');

        $latest = $this->latest($student);
        $this->assertSame('high', $latest->risk_level);
        $this->assertEquals(94.1, (float) $latest->risk_score);
        $this->assertSame('anti_bullying', $latest->risk_factors['recommended_seminar_tag']);
        $this->assertSame($serious->id, $latest->risk_factors['held_by_referral_id']);
        $this->assertSame('low', $latest->risk_factors['ml_risk_level']);
        $this->assertEquals(30, (float) $latest->risk_factors['ml_risk_score']);
        $this->assertSame('Late to class once.', $latest->risk_factors['reason'], "the new incident's own text is still recorded");

        // The new referral's own priority still reflects ITS incident, not the held case.
        $this->assertSame('low', $mild->fresh()->priority);
    }

    public function test_a_higher_new_score_is_not_held_back(): void
    {
        $this->predictSequence([['moderate', 60, 'values_formation'], ['high', 97, 'anti_bullying']]);
        $student = Student::factory()->create();

        $this->file($student);
        $this->file($student);

        $latest = $this->latest($student);
        $this->assertEquals(97, (float) $latest->risk_score);
        $this->assertArrayNotHasKey('held_by_referral_id', $latest->risk_factors);
    }

    public function test_a_resolved_referral_no_longer_holds_the_score(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying'], ['low', 30, 'orientation']]);
        $student = Student::factory()->create();

        $serious = $this->file($student);
        $serious->update(['status' => 'resolved']);
        $this->file($student);

        $latest = $this->latest($student);
        $this->assertSame('low', $latest->risk_level);
        $this->assertEquals(30, (float) $latest->risk_score);
        $this->assertArrayNotHasKey('held_by_referral_id', $latest->risk_factors);
    }

    public function test_a_cancelled_referral_no_longer_holds_the_score(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying'], ['low', 30, 'orientation']]);
        $student = Student::factory()->create();

        $this->file($student)->update(['status' => 'cancelled']);
        $this->file($student);

        $this->assertSame('low', $this->latest($student)->risk_level);
    }

    public function test_another_students_open_case_holds_nothing(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying'], ['low', 30, 'orientation']]);

        $this->file(Student::factory()->create());
        $other = Student::factory()->create();
        $this->file($other);

        $this->assertSame('low', $this->latest($other)->risk_level);
    }

    public function test_the_periodic_recheck_does_not_decay_the_score_while_a_case_is_open(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying'], ['low', 20, 'orientation'], ['low', 20, 'orientation']]);
        $student = Student::factory()->create();
        $referral = $this->file($student);
        $service = app(RiskAssessmentService::class);

        $held = $service->reassessOverTime($student->fresh());
        $this->assertSame('high', $held->risk_level, 'open case keeps the score up');

        $referral->update(['status' => 'resolved']);
        $released = $service->reassessOverTime($student->fresh());
        $this->assertSame('low', $released->risk_level, 'once resolved, the recheck can lower it again');
    }

    public function test_the_list_and_detail_pages_explain_a_held_score(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying'], ['low', 30, 'orientation']]);
        $student = Student::factory()->create();
        $serious = $this->file($student);
        $this->file($student);
        $counselor = User::factory()->counselor()->create();

        $list = $this->actingAs($counselor)->get(route('admin.risk.index'))->getContent();
        $this->assertStringContainsString('>held<', $list);
        $this->assertStringContainsString("Held by open referral #{$serious->id}", $list);

        $detail = $this->actingAs($counselor)->get(route('admin.risk.show', $student->id))->getContent();
        $this->assertStringContainsString('Score held by an open case', $detail);
        $this->assertStringContainsString("#{$serious->id}", $detail);
    }

    public function test_an_unheld_assessment_shows_no_held_marker(): void
    {
        $this->predictSequence([['high', 94.1, 'anti_bullying']]);
        $student = Student::factory()->create();
        $this->file($student);
        $counselor = User::factory()->counselor()->create();

        $this->assertStringNotContainsString('>held<', $this->actingAs($counselor)->get(route('admin.risk.index'))->getContent());
        $this->assertStringNotContainsString('Score held by an open case', $this->actingAs($counselor)->get(route('admin.risk.show', $student->id))->getContent());
    }
}

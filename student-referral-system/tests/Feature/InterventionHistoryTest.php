<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\Referral;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Details page used to only ever show interventions tied to the CURRENT
 * referral — a student with three separate referrals over the year had no
 * single place showing the pattern of what's already been tried, unless a
 * counselor happened to check each referral individually.
 */
class InterventionHistoryTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_shows_interventions_from_the_students_other_referrals(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();

        $referralA = Referral::factory()->create(['student_id' => $student->id]);
        $referralB = Referral::factory()->create(['student_id' => $student->id]);

        $current = Intervention::factory()->create([
            'referral_id' => $referralA->id,
            'counselor_id' => $counselor->id,
            'intervention_type' => 'One-on-One Counseling',
        ]);
        $earlier = Intervention::factory()->create([
            'referral_id' => $referralB->id,
            'counselor_id' => $counselor->id,
            'intervention_type' => 'Group Counseling',
            'intervention_date' => now()->subMonth(),
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $current->id));

        // Not asserting on intervention_type text here — the Edit modal's
        // own dropdown always lists every type, so that text is present on
        // every Details page regardless of this feature. Ref # is unique to
        // an actually-rendered history row or the Linked Referral card.
        $page->assertSee("Other Interventions");
        $page->assertSee('Ref #' . str_pad($referralB->id, 4, '0', STR_PAD_LEFT));
        $this->assertTrue($page->viewData('otherInterventions')->pluck('id')->contains($earlier->id));
    }

    public function test_does_not_show_itself_in_its_own_history(): void
    {
        $counselor = $this->counselor();
        $student = Student::factory()->create();
        $referral = Referral::factory()->create(['student_id' => $student->id]);
        $intervention = Intervention::factory()->create([
            'referral_id' => $referral->id,
            'counselor_id' => $counselor->id,
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $intervention->id));

        $page->assertDontSee("Other Interventions");
    }

    public function test_does_not_show_a_different_students_interventions(): void
    {
        $counselor = $this->counselor();
        $studentA = Student::factory()->create();
        $studentB = Student::factory()->create();

        $referralA = Referral::factory()->create(['student_id' => $studentA->id]);
        $referralB = Referral::factory()->create(['student_id' => $studentB->id]);

        $current = Intervention::factory()->create([
            'referral_id' => $referralA->id,
            'counselor_id' => $counselor->id,
        ]);
        $otherStudentsIntervention = Intervention::factory()->create([
            'referral_id' => $referralB->id,
            'counselor_id' => $counselor->id,
        ]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.show', $current->id));

        // The Edit modal's own type dropdown always lists every possible
        // intervention type, so asserting on type text here would give a
        // false pass/fail depending on which type Faker happened to pick —
        // check the actual "other student's" record isn't in the history
        // view data instead.
        $page->assertDontSee('Ref #' . str_pad($referralB->id, 4, '0', STR_PAD_LEFT));
        $this->assertFalse($page->viewData('otherInterventions')->pluck('id')->contains($otherStudentsIntervention->id));
    }
}

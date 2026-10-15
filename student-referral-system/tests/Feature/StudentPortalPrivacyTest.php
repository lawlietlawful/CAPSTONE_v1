<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\RiskAssessment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * What a student can see about their own casework: where a referral stands
 * and who is helping — never what a teacher wrote, who referred them, or
 * their risk label or score.
 */
class StudentPortalPrivacyTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_student_sees_type_status_date_and_counselor_only(): void
    {
        $student = Student::factory()->create();
        $teacher = User::factory()->teacher()->create(['name' => 'Sir Santos']);
        $counselor = User::factory()->create(['role' => 'admin', 'name' => 'Rosa Santos']);
        Referral::factory()->create([
            'student_id' => $student->id, 'referred_by' => $teacher->id, 'counselor_id' => $counselor->id,
            'referral_type' => 'Misconduct', 'status' => 'in_progress', 'priority' => 'high',
            'reason' => '[AUTO-ESCALATED from Behavioral Report #3] [Flagged: description names violence/a weapon/a threat] Pushed a classmate.',
        ]);
        Sanctum::actingAs($student->user);

        $response = $this->getJson('/api/student/referrals')->assertOk()
            ->assertJsonPath('has_active_referral', true)
            ->assertJsonPath('active_referral.type_label', 'Misconduct')
            ->assertJsonPath('active_referral.status', 'in_progress')
            ->assertJsonPath('active_referral.counselor_name', 'Rosa Santos');

        $this->assertSame(
            ['id', 'type_label', 'status', 'counselor_name', 'created_at', 'resolved_at'],
            array_keys($response->json('active_referral')),
        );
        $response->assertDontSee('Pushed a classmate')
            ->assertDontSee('Flagged')
            ->assertDontSee('Sir Santos')
            ->assertDontSee('high');
    }

    public function test_a_student_cannot_get_their_risk_label_or_score(): void
    {
        $student = Student::factory()->create();
        RiskAssessment::create(['student_id' => $student->id, 'risk_score' => 82, 'risk_level' => 'high', 'assessed_at' => now()]);
        Sanctum::actingAs($student->user);

        $this->getJson('/api/student/risk-level')->assertNotFound();
        foreach (['/api/student/profile', '/api/student/referrals', '/api/student/seminars', '/api/student/notifications'] as $url) {
            $this->getJson($url)->assertOk()->assertDontSee('risk_level')->assertDontSee('risk_score');
        }
    }

    public function test_a_student_signing_in_on_the_website_lands_on_a_working_page(): void
    {
        $student = Student::factory()->create();
        $student->user->update(['name' => 'Ana Cruz']);

        $this->actingAs($student->user)->get(route('student.dashboard'))->assertOk()
            ->assertSee('Hi, Ana')
            ->assertSee('data-student-home', false)
            ->assertSee('Use the Student Portal app');
    }
}

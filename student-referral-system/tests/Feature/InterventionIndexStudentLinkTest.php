<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The student name in the index table used to link to the student's own
 * profile page — inconsistent with a table that lists many interventions
 * per student, where clicking a specific row's name should go to that
 * row's own intervention (matching what "View Details" already does), the
 * same correction made earlier for the Referral index page.
 */
class InterventionIndexStudentLinkTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_student_name_links_to_that_rows_own_intervention(): void
    {
        $counselor = $this->counselor();
        $intervention = Intervention::factory()->create(['counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));

        $page->assertSee(route('counselor.interventions.show', $intervention->id), false);
        $page->assertDontSee(route('admin.students.show', $intervention->referral->student_id), false);
    }
}

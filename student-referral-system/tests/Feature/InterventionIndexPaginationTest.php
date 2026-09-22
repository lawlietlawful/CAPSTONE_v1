<?php

namespace Tests\Feature;

use App\Models\Intervention;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The Intervention index used to paginate at 15 per page; lowered to 10 to
 * match the Referral index page's own page size.
 */
class InterventionIndexPaginationTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_index_paginates_at_ten_per_page(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->count(12)->create(['counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));

        $interventions = $page->viewData('interventions');
        $this->assertSame(10, $interventions->count());
        $this->assertSame(12, $interventions->total());
        $page->assertSee('Showing');
    }

    public function test_second_page_shows_the_remaining_records(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->count(12)->create(['counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index', ['page' => 2]));

        $this->assertSame(2, $page->viewData('interventions')->count());
    }

    public function test_no_pagination_controls_when_ten_or_fewer(): void
    {
        $counselor = $this->counselor();
        Intervention::factory()->count(5)->create(['counselor_id' => $counselor->id]);

        $page = $this->actingAs($counselor)->get(route('counselor.interventions.index'));

        $this->assertFalse($page->viewData('interventions')->hasPages());
    }
}

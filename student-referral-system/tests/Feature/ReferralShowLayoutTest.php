<?php

namespace Tests\Feature;

use App\Models\Referral;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Layout guards for the counselor Referral detail page. The right column was up
 * to ~300px shorter than the left, leaving a blank strip under Update Referral.
 * Both columns are flex columns now and the last right card grows to the common
 * edge, with the notes box taking the extra height.
 */
class ReferralShowLayoutTest extends TestCase
{
    use RefreshDatabase;

    private function counselor(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    private function page(User $viewer, Referral $r): string
    {
        return $this->actingAs($viewer)->get(route('counselor.referrals.show', $r->id))->assertOk()->getContent();
    }

    public function test_both_columns_are_flex_columns(): void
    {
        $c = $this->counselor();
        $html = $this->page($c, Referral::factory()->create());

        $this->assertStringContainsString('lg:col-span-2 flex flex-col gap-6', $html);
        $this->assertStringContainsString('lg:col-span-1 flex flex-col gap-6', $html);
        $this->assertStringNotContainsString('lg:col-span-2 space-y-6', $html);
        $this->assertStringNotContainsString('lg:col-span-1 space-y-6', $html);
    }

    public function test_update_referral_is_the_last_right_card_and_grows_to_the_common_edge(): void
    {
        $c = $this->counselor();
        $html = $this->page($c, Referral::factory()->create());

        $this->assertSame(1, substr_count($html, 'data-update-referral'));
        $this->assertMatchesRegularExpression('#overflow-hidden flex-1 flex flex-col" data-update-referral>#', $html);
        $this->assertGreaterThan(strpos($html, 'AI Risk Assessment'), strpos($html, 'data-update-referral'), 'after the assessment card');
        $this->assertStringContainsString('class="flex-1 min-h-[5rem] w-full rounded-xl', $html, 'notes box absorbs the spare height');
    }

    public function test_the_update_form_is_unchanged_in_what_it_submits(): void
    {
        $c = $this->counselor();
        $r = Referral::factory()->create(['status' => 'pending']);

        $html = $this->page($c, $r);

        $this->assertStringContainsString(route('counselor.referrals.updateStatus', $r->id), $html);
        foreach (['name="status"', 'name="counselor_id"', 'name="counselor_notes"'] as $field) {
            $this->assertStringContainsString($field, $html);
        }

        $this->actingAs($c)->patch(route('counselor.referrals.updateStatus', $r->id), [
            'status' => 'in_progress', 'counselor_id' => $c->id, 'counselor_notes' => 'Met the student.',
        ])->assertRedirect();

        $this->assertSame('in_progress', $r->fresh()->status);
        $this->assertSame('Met the student.', $r->fresh()->counselor_notes);
    }
}

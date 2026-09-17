<?php

namespace Tests\Unit;

use App\Models\Course;
use Tests\TestCase;

/**
 * Course::abbreviate() — derives a short acronym for a full program name
 * since there's no separate abbreviation column in the catalog. Used to
 * keep the Students table's course badge compact (a full program name in
 * a rounded-full pill stretches into an odd capsule shape).
 */
class CourseAbbreviationTest extends TestCase
{
    public function test_abbreviates_a_typical_program_name(): void
    {
        $this->assertSame('BSIT', Course::abbreviate('Bachelor of Science in Information Technology'));
    }

    public function test_skips_small_connector_words(): void
    {
        // "Education" abbreviates to "Ed" (BSEd/BEEd), not "E" (BSE/BEE) —
        // the standard convention for these Philippine education degrees.
        $this->assertSame('BSEd', Course::abbreviate('Bachelor of Secondary Education'));
        $this->assertSame('BEEd', Course::abbreviate('Bachelor of Elementary Education'));
    }

    public function test_falls_back_to_the_original_name_for_a_single_word_program(): void
    {
        $this->assertSame('Nursing', Course::abbreviate('Nursing'));
    }

    public function test_returns_na_for_a_missing_course(): void
    {
        $this->assertSame('N/A', Course::abbreviate(null));
        $this->assertSame('N/A', Course::abbreviate(''));
    }

    public function test_is_case_insensitive_about_connector_words(): void
    {
        $this->assertSame('BSIT', Course::abbreviate('Bachelor OF Science IN Information Technology'));
    }
}

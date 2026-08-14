<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Adds an 'Unassessed' severity so a behavioral report filed while the ML
 * engine is unreachable can say so, instead of being silently graded 'Low'.
 *
 * A silent 'Low' is the worst failure mode for an early-intervention system:
 * a serious incident looks harmless, never escalates to Guidance, and nothing
 * in the record shows that the assessment never ran.
 *
 * 'minor'/'moderate'/'severe' are legacy values retained so existing rows and
 * the admin severity filter keep working.
 */
return new class extends Migration
{
    private const WITH_UNASSESSED = "ENUM('minor','moderate','severe','Low','Medium','High','Critical','Unassessed')";
    private const WITHOUT_UNASSESSED = "ENUM('minor','moderate','severe','Low','Medium','High','Critical')";

    public function up(): void
    {
        DB::statement(
            'ALTER TABLE behavioral_reports MODIFY severity ' . self::WITH_UNASSESSED . " NULL DEFAULT 'Low'"
        );
    }

    public function down(): void
    {
        // A report still awaiting assessment has no meaningful grade to fall
        // back to. Park it at NULL rather than inventing a severity that would
        // make an unassessed incident look triaged.
        DB::table('behavioral_reports')->where('severity', 'Unassessed')->update(['severity' => null]);

        DB::statement(
            'ALTER TABLE behavioral_reports MODIFY severity ' . self::WITHOUT_UNASSESSED . " NULL DEFAULT 'Low'"
        );
    }
};

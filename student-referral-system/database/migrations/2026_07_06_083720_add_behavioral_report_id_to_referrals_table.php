<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->foreignId('behavioral_report_id')
                  ->nullable()
                  ->after('risk_assessment_id')
                  ->constrained('behavioral_reports')
                  ->onDelete('set null');
        });

        // Backfill: some existing referrals were auto-escalated from a
        // behavioral report before this column existed, and only recorded
        // the link as free text in `reason` (e.g. "[AUTO-ESCALATED from
        // Behavioral Report #7] ..."). Recover the real link where possible.
        DB::table('referrals')
            ->where('reason', 'like', '%AUTO-ESCALATED from Behavioral Report #%')
            ->orderBy('id')
            ->each(function ($referral) {
                if (preg_match('/Behavioral Report #(\d+)/', $referral->reason, $matches)) {
                    $reportId = (int) $matches[1];
                    $exists = DB::table('behavioral_reports')->where('id', $reportId)->exists();
                    if ($exists) {
                        DB::table('referrals')
                            ->where('id', $referral->id)
                            ->update(['behavioral_report_id' => $reportId]);
                    }
                }
            });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('behavioral_report_id');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * After Guidance resolves a referral, the teacher who filed it can say how
 * the student is doing in class now — the only view of the outcome from
 * where the problem was first noticed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->string('teacher_followup_outcome', 20)->nullable()->after('resolved_at'); // improved | no_change | worse
            $table->text('teacher_followup_note')->nullable()->after('teacher_followup_outcome');
            $table->timestamp('teacher_followup_at')->nullable()->after('teacher_followup_note');
        });
    }

    public function down(): void
    {
        Schema::table('referrals', function (Blueprint $table) {
            $table->dropColumn(['teacher_followup_outcome', 'teacher_followup_note', 'teacher_followup_at']);
        });
    }
};

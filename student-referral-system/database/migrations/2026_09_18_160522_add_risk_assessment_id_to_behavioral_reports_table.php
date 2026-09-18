<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A report that escalates already reaches its RiskAssessment via
     * escalatedReferral->riskAssessment. One that does NOT escalate has no
     * referral to go through — nullOnDelete so a student/RiskAssessment
     * being removed doesn't cascade-delete the incident record itself, it
     * just drops the link.
     */
    public function up(): void
    {
        Schema::table('behavioral_reports', function (Blueprint $table) {
            $table->foreignId('risk_assessment_id')
                  ->nullable()
                  ->after('severity')
                  ->constrained()
                  ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('behavioral_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('risk_assessment_id');
        });
    }
};

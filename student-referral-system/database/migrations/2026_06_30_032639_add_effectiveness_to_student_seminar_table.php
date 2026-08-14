<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_seminars', function (Blueprint $table) {
            $table->decimal('pre_risk_score', 5, 2)->nullable();
            $table->decimal('post_risk_score', 5, 2)->nullable();
            $table->enum('effectiveness', ['improved', 'no_change', 'worse'])->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_seminars', function (Blueprint $table) {
            $table->dropColumn(['pre_risk_score', 'post_risk_score', 'effectiveness']);
        });
    }
};

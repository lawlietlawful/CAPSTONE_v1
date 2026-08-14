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
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->dropColumn('avg_grade');
            $table->integer('tardiness')->default(0)->after('student_id');
            $table->integer('misconduct')->default(0)->after('tardiness');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $table->decimal('avg_grade', 5, 2)->default(0)->after('student_id');
            $table->dropColumn(['tardiness', 'misconduct']);
        });
    }
};

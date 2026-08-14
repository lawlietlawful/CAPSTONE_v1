<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Nullable — only Grade 11/12 sections under a Basic Education course
     * carry a strand (STEM, ABM, HUMSS, ...); every other grade level and
     * every College section leaves this blank.
     */
    public function up(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->string('strand')->nullable()->after('grade_level');
        });
    }

    public function down(): void
    {
        Schema::table('course_sections', function (Blueprint $table) {
            $table->dropColumn('strand');
        });
    }
};

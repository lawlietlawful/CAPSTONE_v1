<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing students are all college enrollees today, so they default to
     * 'College' and keep their current "1st Year"-style grade_level values
     * untouched. strand only applies to Basic Education students in Grade
     * 11/12.
     */
    public function up(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->enum('education_level', ['Basic Education', 'College'])
                  ->default('College')
                  ->after('course');
            $table->string('strand')->nullable()->after('grade_level');
        });
    }

    public function down(): void
    {
        Schema::table('students', function (Blueprint $table) {
            $table->dropColumn(['education_level', 'strand']);
        });
    }
};

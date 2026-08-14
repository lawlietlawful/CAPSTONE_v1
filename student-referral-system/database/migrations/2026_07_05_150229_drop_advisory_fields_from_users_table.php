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
        // Superseded by the teacher_assignments table (one-to-many, backed
        // by real course/grade/section combinations). All call-sites were
        // migrated to User::advisedStudentsQuery() before this ran.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['handled_course', 'handled_grade_level', 'handled_section']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('handled_course')->nullable()->after('role');
            $table->string('handled_grade_level')->nullable()->after('handled_course');
            $table->string('handled_section')->nullable()->after('handled_grade_level');
        });
    }
};

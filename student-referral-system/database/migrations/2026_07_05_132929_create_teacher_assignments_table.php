<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('teacher_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('teacher_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('course');
            $table->string('grade_level')->nullable();
            $table->string('section')->nullable();
            $table->timestamps();

            $table->unique(['teacher_id', 'course', 'grade_level', 'section'], 'teacher_assignments_unique');
        });

        // Backfill: carry forward any existing single-valued advisory
        // assignment into the new one-to-many table before it's retired.
        DB::table('users')
            ->where('role', 'teacher')
            ->whereNotNull('handled_course')
            ->get(['id', 'handled_course', 'handled_grade_level', 'handled_section'])
            ->each(function ($teacher) {
                DB::table('teacher_assignments')->insert([
                    'teacher_id' => $teacher->id,
                    'course' => $teacher->handled_course,
                    'grade_level' => $teacher->handled_grade_level,
                    'section' => $teacher->handled_section,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('teacher_assignments');
    }
};

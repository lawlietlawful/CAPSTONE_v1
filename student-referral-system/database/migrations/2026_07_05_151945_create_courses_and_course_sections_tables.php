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
        Schema::create('courses', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });

        Schema::create('course_sections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('course_id')
                  ->constrained('courses')
                  ->onDelete('cascade');
            $table->string('grade_level');
            $table->string('section');
            $table->timestamps();

            $table->unique(['course_id', 'grade_level', 'section'], 'course_sections_unique');
        });

        $this->backfill();
    }

    /**
     * Seed the catalog from data that already exists, so the picker isn't
     * empty on day one and nothing currently in use (by students or by the
     * teacher_assignments built earlier) goes missing from the dropdowns.
     */
    private function backfill(): void
    {
        $courseNames = DB::table('students')->whereNotNull('course')->distinct()->pluck('course')
            ->merge(DB::table('teacher_assignments')->whereNotNull('course')->distinct()->pluck('course'))
            ->unique()
            ->values();

        $courseIds = [];
        foreach ($courseNames as $name) {
            $courseIds[$name] = DB::table('courses')->insertGetId([
                'name' => $name,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $sectionTriples = DB::table('students')
            ->whereNotNull('course')->whereNotNull('grade_level')->whereNotNull('section')
            ->select('course', 'grade_level', 'section')->distinct()->get()
            ->merge(
                DB::table('teacher_assignments')
                    ->whereNotNull('course')->whereNotNull('grade_level')->whereNotNull('section')
                    ->select('course', 'grade_level', 'section')->distinct()->get()
            )
            ->unique(fn ($row) => $row->course . '|' . $row->grade_level . '|' . $row->section);

        foreach ($sectionTriples as $row) {
            DB::table('course_sections')->insert([
                'course_id' => $courseIds[$row->course],
                'grade_level' => $row->grade_level,
                'section' => $row->section,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('course_sections');
        Schema::dropIfExists('courses');
    }
};

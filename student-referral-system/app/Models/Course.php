<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Course extends Model
{
    protected $fillable = [
        'name',
        'education_level',
    ];

    /**
     * Fixed grade-level lists shown in the Student/Course forms. College
     * year levels and Basic Education (K-12) grades are both closed,
     * DepEd/registrar-standard lists, so they're hardcoded rather than
     * catalog-managed — only the Section (and, for Grade 11/12, the
     * enrollment strand) actually vary per school and stay admin-managed.
     */
    public const COLLEGE_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year', 'Irregular'];

    public const BASIC_ED_GRADE_LEVELS = [
        'Kindergarten',
        'Grade 1', 'Grade 2', 'Grade 3', 'Grade 4', 'Grade 5', 'Grade 6',
        'Grade 7', 'Grade 8', 'Grade 9', 'Grade 10',
        'Grade 11', 'Grade 12',
    ];

    /** DepEd Senior High School academic strands — only relevant for Grade 11/12. */
    public const STRANDS = ['STEM', 'ABM', 'HUMSS', 'GAS', 'TVL', 'Arts and Design', 'Sports'];

    /** Grade levels (Basic Education) that carry a Senior High School strand. */
    public const SENIOR_HIGH_GRADES = ['Grade 11', 'Grade 12'];

    public function sections()
    {
        return $this->hasMany(CourseSection::class);
    }

    /**
     * Flat (course, education_level, grade_level, strand, section) tuples
     * for populating the cascading course/grade/section pickers used on the
     * Student and Teacher assignment forms.
     */
    public static function picklist()
    {
        return CourseSection::with('course')->get()->map(fn ($s) => [
            'course' => $s->course->name,
            'education_level' => $s->course->education_level,
            'grade_level' => $s->grade_level,
            'strand' => $s->strand,
            'section' => $s->section,
        ])->values();
    }
}

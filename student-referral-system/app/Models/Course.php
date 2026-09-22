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
     * A short acronym for a full program name, e.g. "Bachelor of Science in
     * Information Technology" -> "BSIT", for compact UI like table badges.
     * There's no separate abbreviation column — this derives one from the
     * initials of each significant word, skipping small connector words.
     * Falls back to the original name for a single-word program (or no
     * name at all), where a one-letter acronym would lose more than it
     * saves.
     */
    public static function abbreviate(?string $name): string
    {
        if (! $name) {
            return 'N/A';
        }

        $skipWords = ['of', 'in', 'and', 'for', 'the', '&'];
        $significantWords = array_values(array_filter(
            preg_split('/\s+/', trim($name)),
            fn ($word) => $word !== '' && ! in_array(mb_strtolower($word), $skipWords, true)
        ));

        if (count($significantWords) < 2) {
            return $name;
        }

        return collect($significantWords)
            ->map(function ($word) {
                // "Education" conventionally abbreviates to "Ed", not "E" —
                // BSEd/BEEd/MAEd are the standard forms for these degrees,
                // not BSE/BEE/MAE.
                return mb_strtolower($word) === 'education'
                    ? 'Ed'
                    : mb_strtoupper(mb_substr($word, 0, 1));
            })
            ->implode('');
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

    /**
     * Whether a (course, grade_level, section) combination exists in the
     * catalog — the same check the CSV importer already runs on every row
     * (StudentController::previewImport), now shared with the single-student
     * Add/Edit forms so a raw request can't create a student pointing at a
     * course/section that doesn't exist, the way the UI's cascading
     * dropdowns already prevent in practice. Strand is deliberately not
     * part of the match, matching the importer's own check exactly.
     */
    public static function comboExists(?string $course, ?string $gradeLevel, ?string $section): bool
    {
        if (! $course || ! $gradeLevel || ! $section) {
            return false;
        }

        return static::picklist()->contains(fn ($c) =>
            strcasecmp($c['course'], $course) === 0
            && strcasecmp($c['grade_level'], $gradeLevel) === 0
            && strcasecmp($c['section'], $section) === 0
        );
    }
}

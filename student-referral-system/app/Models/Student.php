<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Student extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'student_id_number',
        'course',
        'education_level',
        'first_name',
        'last_name',
        'middle_name',
        'gender',
        'birthdate',
        'grade_level',
        'strand',
        'section',
        'school_year',
        'parent_name',
        'parent_contact',
        'parent_email',
        'student_contact',
        'address',
        'status',
    ];

    protected $casts = [
        'birthdate' => 'date',
    ];

    // Relationships
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function behavioralReports()
    {
        return $this->hasMany(BehavioralReport::class);
    }

    public function riskAssessments()
    {
        return $this->hasMany(RiskAssessment::class);
    }

    /**
     * The student's most recent risk assessment — their *current* risk level.
     * A HasOne via latestOfMany so it can be eager-loaded without an N+1 across
     * a whole roster.
     */
    public function latestRiskAssessment()
    {
        return $this->hasOne(RiskAssessment::class)->latestOfMany();
    }

    public function referrals()
    {
        return $this->hasMany(Referral::class);
    }

    public function seminars()
    {
        return $this->belongsToMany(Seminar::class, 'student_seminars')
                    ->using(StudentSeminar::class)
                    ->withPivot('status', 'assigned_by', 'attended_at', 'remarks')
                    ->withTimestamps();
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class, 'user_id', 'user_id');
    }

    // Helper — get full name
    /**
     * Name / Student ID search, including full names in either order
     * ("Maria Santos", "Santos Maria", "Santos, Maria"). Shared by the
     * Students list and the At-Risk list so they find the same people.
     */
    public function scopeMatchingSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $like = '%' . $term . '%';

        return $query->where(function ($w) use ($like) {
            $w->where('first_name', 'like', $like)
              ->orWhere('last_name', 'like', $like)
              ->orWhere('student_id_number', 'like', $like)
              ->orWhereRaw("CONCAT(first_name, ' ', last_name) like ?", [$like])
              ->orWhereRaw("CONCAT(last_name, ' ', first_name) like ?", [$like])
              ->orWhereRaw("CONCAT(last_name, ', ', first_name) like ?", [$like]);
        });
    }

    /**
     * The search behind the student pickers (Assign Students on a seminar,
     * Submit New Referral): matchingSearch() plus course, year and section,
     * and a course abbreviation ("BSIT") for its full catalog name. The
     * pickers fetch results as you type instead of loading every student.
     */
    public function scopePickerSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        // Every word must match something ("BSN 2nd" = a BSN student in 2nd
        // Year; "maria santos" = first and last name), each word against
        // name, ID, course (or its abbreviation), year and section.
        $courseNames = Course::pluck('name');

        foreach (preg_split('/\s+/', $term) as $word) {
            $like = '%' . $word . '%';
            $abbreviated = $courseNames
                ->filter(fn ($name) => str_starts_with(mb_strtolower(Course::abbreviate($name)), mb_strtolower($word)))
                ->values()->all();

            $query->where(function ($w) use ($word, $like, $abbreviated) {
                $w->matchingSearch($word)
                  ->orWhere('course', 'like', $like)
                  ->orWhere('grade_level', 'like', $like)
                  ->orWhere('section', 'like', $like)
                  ->when($abbreviated, fn ($q) => $q->orWhereIn('course', $abbreviated));
            });
        }

        return $query;
    }

    /** One row for a student picker (JSON). */
    public function toPickerArray(): array
    {
        return [
            'id'    => (string) $this->id,
            'label' => "{$this->last_name}, {$this->first_name}",
            'idNo'  => $this->student_id_number,
            'meta'  => collect([$this->course ? Course::abbreviate($this->course) : null, $this->grade_level, $this->section])->filter()->implode(' · '),
            'course' => $this->course,
        ];
    }

    public function getFullNameAttribute()
    {
        return $this->first_name . ' ' . $this->last_name;
    }

    // Helper — get latest risk level
    public function getLatestRiskLevelAttribute()
    {
        $latest = $this->riskAssessments()->latest()->first();
        return $latest ? $latest->risk_level : 'not assessed';
    }
}

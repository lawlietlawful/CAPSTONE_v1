<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Seminar extends Model
{
    protected $fillable = [
        'title',
        'description',
        'date',
        'time',
        'venue',
        'speaker',
        'is_required',
        'target_grade_level',
        'target_course',
        'max_participants',
        'status',
        'trigger_reason',
    ];

    protected $casts = [
        'date'        => 'date',
        'is_required' => 'boolean',
    ];

    // Relationships
    public function students()
    {
        return $this->belongsToMany(Student::class, 'student_seminars')
                    ->using(StudentSeminar::class)
                    ->withPivot('status', 'assigned_by', 'attended_at', 'remarks', 'pre_risk_score', 'post_risk_score', 'effectiveness')
                    ->withTimestamps();
    }

    // Helper — count attended students
    public function getTotalAttendeesAttribute()
    {
        return $this->students()->count();
    }
}

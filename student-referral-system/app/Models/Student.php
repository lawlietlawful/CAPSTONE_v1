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

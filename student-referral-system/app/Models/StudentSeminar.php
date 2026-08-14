<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class StudentSeminar extends Pivot
{
    protected $table = 'student_seminars';

    // This pivot table has its own auto-incrementing `id` (not a composite key).
    public $incrementing = true;

    protected $fillable = [
        'student_id',
        'seminar_id',
        'status',
        'assigned_by',
        'attended_at',
        'remarks',
        'pre_risk_score',
        'post_risk_score',
        'effectiveness',
    ];

    protected $casts = [
        'attended_at' => 'datetime',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function seminar()
    {
        return $this->belongsTo(Seminar::class);
    }
}

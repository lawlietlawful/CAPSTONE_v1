<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CourseSection extends Model
{
    protected $fillable = [
        'course_id',
        'grade_level',
        'strand',
        'section',
    ];

    public function course()
    {
        return $this->belongsTo(Course::class);
    }
}

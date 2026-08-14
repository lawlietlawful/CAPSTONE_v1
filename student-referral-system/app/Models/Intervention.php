<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Intervention extends Model
{
    protected $fillable = [
        'referral_id',
        'counselor_id',
        'intervention_type',
        'description',
        'intervention_date',
        'outcome',
        'follow_up_notes',
        'follow_up_date',
    ];

    protected $casts = [
        'intervention_date' => 'date',
        'follow_up_date'    => 'date',
    ];

    // Relationships
    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function counselor()
    {
        return $this->belongsTo(User::class, 'counselor_id');
    }
}

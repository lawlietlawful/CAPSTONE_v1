<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SmsLog extends Model
{
    protected $fillable = [
        'referral_id',
        'student_id',
        'recipient_name',
        'recipient_number',
        'recipient_type',
        'message',
        'status',
        'sms_provider',
        'sent_at',
        'error_message',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
    ];

    // Relationships
    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }

    public function student()
    {
        return $this->belongsTo(Student::class);
    }
}

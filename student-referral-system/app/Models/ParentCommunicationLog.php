<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ParentCommunicationLog extends Model
{
    protected $fillable = [
        'referral_id',
        'contact_method',
        'summary',
    ];

    public function referral()
    {
        return $this->belongsTo(Referral::class);
    }
}

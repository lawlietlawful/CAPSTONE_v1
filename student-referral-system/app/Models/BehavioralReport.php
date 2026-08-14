<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class BehavioralReport extends Model
{
    use HasFactory;

    /**
     * The fixed set of incident types a teacher may file, as `value => label`.
     *
     * Single source of truth for the web forms, the API validation rule, and the
     * mobile app (served by TeacherPortalController::reports()). These strings
     * are NOT cosmetic: BehavioralReportService keys its escalation and
     * concern-type derivation off the exact value, so a client posting
     * "academic failure" would file a report that silently never escalates.
     * `incident_type` was previously validated only as `string|max:100`.
     */
    public const INCIDENT_TYPES = [
        'Academic Failure'      => 'Academic Failure / Failing Grade',
        'Truancy'               => 'Truancy / Cutting Classes',
        'Disciplinary Incident' => 'Disciplinary Incident',
        'Other'                 => 'Other (Specify in description)',
    ];

    /** The values a client may submit. */
    public static function incidentTypeValues(): array
    {
        return array_keys(self::INCIDENT_TYPES);
    }

    protected $fillable = [
        'student_id',
        'reported_by',
        'incident_type',
        'description',
        'severity',
        'incident_date',
        'location',
        'status',
        'counselor_notes',
    ];

    protected $casts = [
        'incident_date' => 'date',
    ];

    // Relationships
    public function student()
    {
        return $this->belongsTo(Student::class);
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    /**
     * The referral this report auto-escalated into, if its severity was
     * High/Critical at submission time. Null for reports that stayed as a
     * plain incident log.
     */
    public function escalatedReferral()
    {
        return $this->hasOne(Referral::class);
    }
}

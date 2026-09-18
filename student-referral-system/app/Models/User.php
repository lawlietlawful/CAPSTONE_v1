<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
        'role',
        'account_activated_at',
        'activation_code',
    ];

    protected $hidden = [
        'password',
        'remember_token',
        'activation_code',
    ];

    /**
     * A one-time teacher activation code, e.g. "7QK-3F9". Drawn from an
     * unambiguous alphabet (no 0/O/1/I) so it's easy to read aloud and type.
     * Returned in plain text — the caller shows it to the admin once and stores
     * only its hash (see Admin\UserController).
     */
    public static function generateActivationCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
        $pick = fn (int $n) => collect(range(1, $n))
            ->map(fn () => $alphabet[random_int(0, strlen($alphabet) - 1)])
            ->implode('');

        return $pick(3) . '-' . $pick(3);
    }

    /**
     * Normalise an activation code for hashing/comparison: strip the display
     * hyphen (and any stray spaces) and upper-case it, so a teacher typing
     * "7qk 3f9" still matches the stored "7QK-3F9".
     */
    public static function canonicalActivationCode(string $code): string
    {
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $code));
    }

    protected $casts = [
        'account_activated_at' => 'datetime',
    ];

    // Relationships
    public function student()
    {
        return $this->hasOne(Student::class);
    }

    public function referralsReferred()
    {
        return $this->hasMany(Referral::class, 'referred_by');
    }

    public function referralsCounselor()
    {
        return $this->hasMany(Referral::class, 'counselor_id');
    }

    public function interventions()
    {
        return $this->hasMany(Intervention::class, 'counselor_id');
    }

    public function notifications()
    {
        return $this->hasMany(Notification::class);
    }

    public function behavioralReports()
    {
        return $this->hasMany(BehavioralReport::class, 'reported_by');
    }

    public function teacherAssignments()
    {
        return $this->hasMany(TeacherAssignment::class, 'teacher_id');
    }

    /**
     * Replace this teacher's course assignments with the submitted set.
     * Rows with a blank course are silently skipped (an empty leftover row
     * from the repeatable UI shouldn't be treated as a real assignment).
     * A blank grade level or section is stored as null, meaning "whole
     * course" or "whole grade level" respectively.
     */
    public function syncTeacherAssignments(array $assignments): void
    {
        $this->teacherAssignments()->delete();

        foreach ($assignments as $assignment) {
            if (empty($assignment['course'])) {
                continue;
            }

            $this->teacherAssignments()->create([
                'course' => $assignment['course'],
                'grade_level' => ($assignment['grade_level'] ?? '') ?: null,
                'section' => ($assignment['section'] ?? '') ?: null,
            ]);
        }
    }

    /**
     * Students this teacher is allowed to see, based on their course/grade
     * level/section assignments. Fails closed: a teacher with no assignments
     * yet sees no students, rather than defaulting to the entire school.
     */
    public function advisedStudentsQuery()
    {
        $assignments = $this->teacherAssignments;

        if ($assignments->isEmpty()) {
            return Student::whereRaw('1 = 0');
        }

        return Student::where('status', 'active')->where(function ($q) use ($assignments) {
            foreach ($assignments as $assignment) {
                $q->orWhere(function ($sub) use ($assignment) {
                    $sub->where('course', $assignment->course);
                    if ($assignment->grade_level) {
                        $sub->where('grade_level', $assignment->grade_level);
                    }
                    if ($assignment->section) {
                        $sub->where('section', $assignment->section);
                    }
                });
            }
        });
    }

    // Role checker helpers
    public function isAdmin()
    {
        return in_array($this->role, ['admin', 'super_admin']);
    }

    public function isCounselor()
    {
        // The former 'guidance_counselor' role is now 'admin'
        return $this->role === 'admin';
    }

    public function isTeacher()
    {
        return $this->role === 'teacher';
    }

    public function isStudent()
    {
        return $this->role === 'student';
    }

    public function isActivated()
    {
        return $this->account_activated_at !== null;
    }
}

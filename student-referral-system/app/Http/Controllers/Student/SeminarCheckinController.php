<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Seminar;
use App\Models\StudentSeminar;
use Illuminate\Http\Request;

class SeminarCheckinController extends Controller
{
    public function checkin(Seminar $seminar)
    {
        $user = auth()->user();
        if ($user->role !== 'student' || !$user->student) {
            abort(403, 'Only students can check in to seminars.');
        }

        // Time/status gating: a QR check-in should only be possible for a live
        // seminar on its scheduled day — never for a cancelled seminar, and
        // not days early or long after it has passed.
        if ($seminar->status === 'cancelled') {
            return view('student.seminars.checkin_result', [
                'success' => false,
                'message' => 'This seminar has been cancelled. Check-in is not available.',
                'seminar' => $seminar,
            ]);
        }

        $today = now()->startOfDay();
        $seminarDay = \Carbon\Carbon::parse($seminar->date)->startOfDay();

        if ($today->lt($seminarDay)) {
            return view('student.seminars.checkin_result', [
                'success' => false,
                'message' => 'Check-in is not open yet. It opens on the day of the seminar (' . $seminarDay->format('M d, Y') . ').',
                'seminar' => $seminar,
            ]);
        }

        if ($today->gt($seminarDay)) {
            return view('student.seminars.checkin_result', [
                'success' => false,
                'message' => 'The check-in window for this seminar has already closed.',
                'seminar' => $seminar,
            ]);
        }

        $studentId = $user->student->id;

        $enrollment = StudentSeminar::where('student_id', $studentId)
            ->where('seminar_id', $seminar->id)
            ->first();

        if (!$enrollment) {
            return view('student.seminars.checkin_result', [
                'success' => false,
                'message' => 'You are not enrolled in this seminar.',
                'seminar' => $seminar
            ]);
        }

        if ($enrollment->status === 'attended') {
            return view('student.seminars.checkin_result', [
                'success' => true,
                'message' => 'You have already checked in to this seminar. Thank you!',
                'seminar' => $seminar
            ]);
        }

        $enrollment->update([
            'status' => 'attended',
            'attended_at' => now(),
        ]);

        return view('student.seminars.checkin_result', [
            'success' => true,
            'message' => 'Check-in successful! Your attendance has been recorded.',
            'seminar' => $seminar
        ]);
    }
}

<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Student;
use App\Models\Referral;

class TeacherDashboardController extends Controller
{
    public function index()
    {
        $teacher = auth()->user();

        // Total students the teacher manages (based on their course/grade
        // level/section assignments — none yet means none visible).
        $totalStudents = $teacher->advisedStudentsQuery()->count();

        // Referrals filed by this teacher
        $myReferrals = Referral::where('referred_by', auth()->id())->count();
        $pendingReferrals = Referral::where('referred_by', auth()->id())
                                      ->where('status', 'pending')->count();

        // Recent referrals filed by this teacher (for the "Students I Referred" panel)
        $recentReferrals = Referral::with('student')
            ->where('referred_by', auth()->id())
            ->latest()
            ->take(5)
            ->get();

        return view('teacher.dashboard', compact(
            'totalStudents',
            'myReferrals',
            'pendingReferrals',
            'recentReferrals'
        ));
    }
}

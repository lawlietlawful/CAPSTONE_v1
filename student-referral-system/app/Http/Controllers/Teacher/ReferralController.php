<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Services\ReferralService;

class ReferralController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        // Teacher only sees referrals they filed
        $referrals = Referral::with(['student', 'counselor'])
            ->where('referred_by', auth()->id())
            ->latest()
            ->paginate(15);
            
        $pendingCount = Referral::where('referred_by', auth()->id())->where('status', 'pending')->count();

        $students = auth()->user()->advisedStudentsQuery()->orderBy('last_name')->get();

        return view('teacher.referrals.index', compact('referrals', 'pendingCount', 'students'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(Request $request)
    {
        // Students within the current teacher's course/grade/section assignments.
        $students = auth()->user()->advisedStudentsQuery()->orderBy('last_name')->get();
        // If a specific student was passed via query parameter (e.g. from attendance warning)
        $selectedStudentId = $request->query('student_id');

        return view('teacher.referrals.create', compact('students', 'selectedStudentId'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, ReferralService $service)
    {
        $data = $request->validate([
            'student_id'          => 'required|exists:students,id',
            'referral_type'       => 'required|in:' . implode(',', Referral::REFERRAL_TYPES),
            'referral_type_other' => 'nullable|required_if:referral_type,Other|string|max:255',
            // concern_type is a separate, broader categorization used for
            // analytics grouping. Not every referral form asks for it
            // directly, so the service derives it from referral_type.
            'concern_type'        => 'nullable|in:academic,behavioral,emotional,family,peer_conflict,attendance,other',
            'reason'              => 'required|string',
        ]);

        // Persistence, ML risk assessment and the parent SMS all live in the
        // shared service so the web form and the mobile API behave the same.
        $service->create($request->user(), $data);

        return redirect()->route('teacher.referrals.index')
            ->with('success', 'Referral submitted. The AI has automatically assessed the student and assigned an intervention if required.');
    }
}

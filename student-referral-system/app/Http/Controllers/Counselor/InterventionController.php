<?php

namespace App\Http\Controllers\Counselor;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Intervention;
use App\Models\Referral;

class InterventionController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Intervention::with(['referral.student', 'counselor'])
            ->where('counselor_id', auth()->id())
            ->latest('intervention_date');

        // Search by student name
        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('referral.student', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                  ->orWhere('last_name', 'like', "%{$search}%")
                  ->orWhere('student_id_number', 'like', "%{$search}%");
            });
        }

        // Filter by outcome
        if ($request->filled('outcome')) {
            $query->where('outcome', $request->outcome);
        }

        $interventions = $query->paginate(15)->appends($request->query());

        // Stats
        $totalInterventions = Intervention::where('counselor_id', auth()->id())->count();
        $improvingCount = Intervention::where('counselor_id', auth()->id())->where('outcome', 'improving')->count();
        $resolvedCount = Intervention::where('counselor_id', auth()->id())->where('outcome', 'resolved')->count();

        return view('counselor.interventions.index', compact(
            'interventions', 'totalInterventions', 'improvingCount', 'resolvedCount'
        ));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        // Get referrals assigned to this counselor (or general pending) that aren't fully resolved yet
        $referrals = Referral::with('student')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();

        $interventionTypes = [
            'One-on-One Counseling',
            'Group Counseling',
            'Parent-Teacher Conference',
            'Disciplinary Warning',
            'Academic Coaching',
            'Behavioral Contract',
            'Psychological First Aid'
        ];

        return view('counselor.interventions.create', compact('referrals', 'interventionTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, \App\Services\SmsService $smsService)
    {
        $request->validate([
            'referral_id'       => 'required|exists:referrals,id',
            'intervention_type' => 'required|string|max:150',
            'intervention_date' => 'required|date',
            'description'       => 'required|string',
            'outcome'           => 'nullable|in:improving,no_change,worsening,resolved',
            'follow_up_date'    => 'nullable|date|after_or_equal:intervention_date',
            'follow_up_notes'   => 'nullable|string',
        ]);

        $intervention = Intervention::create([
            'referral_id'       => $request->referral_id,
            'counselor_id'      => auth()->id(),
            'intervention_type' => $request->intervention_type,
            'intervention_date' => $request->intervention_date,
            'description'       => $request->description,
            'outcome'           => $request->outcome,
            'follow_up_date'    => $request->follow_up_date,
            'follow_up_notes'   => $request->follow_up_notes,
        ]);

        // Auto-update referral status if needed
        $referral = Referral::find($request->referral_id);
        if ($referral->status === 'pending') {
            $referral->update(['status' => 'in_progress', 'counselor_id' => auth()->id()]);
        }
        if ($request->outcome === 'resolved') {
            $referral->update(['status' => 'resolved', 'resolved_at' => now()]);
        }

        // TRIGGER SMS TO PARENT
        $student = $referral->student;
        if ($student && !empty($student->parent_contact)) {
            $date = \Carbon\Carbon::parse($request->intervention_date)->format('M d, Y');
            $outcomeStr = $request->outcome ? ucfirst(str_replace('_', ' ', $request->outcome)) : 'Pending';
            
            $message = "MU Guidance: A {$request->intervention_type} session was conducted for your child {$student->first_name} on {$date}. Outcome: {$outcomeStr}.";
            
            $smsService->sendSms(
                $student->parent_contact,
                $message,
                $student->id,
                $student->parent_name ?? 'Parent',
                'parent',
                $referral->id
            );
        }

        return redirect()->route('counselor.interventions.index')
            ->with('success', 'Intervention logged successfully. SMS notification sent to parent.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Intervention $intervention)
    {
        // Optional authorization check
        if ($intervention->counselor_id !== auth()->id() && auth()->user()->role !== 'admin') {
            abort(403);
        }

        $intervention->load('referral.student', 'counselor');

        return view('counselor.interventions.show', compact('intervention'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Intervention $intervention)
    {
        if ($intervention->counselor_id !== auth()->id()) {
            abort(403);
        }

        $intervention->load('referral.student');

        $interventionTypes = [
            'One-on-One Counseling',
            'Group Counseling',
            'Parent-Teacher Conference',
            'Disciplinary Warning',
            'Academic Coaching',
            'Behavioral Contract',
            'Psychological First Aid'
        ];

        return view('counselor.interventions.edit', compact('intervention', 'interventionTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Intervention $intervention)
    {
        if ($intervention->counselor_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'intervention_type' => 'required|string|max:150',
            'intervention_date' => 'required|date',
            'description'       => 'required|string',
            'outcome'           => 'nullable|in:improving,no_change,worsening,resolved',
            'follow_up_date'    => 'nullable|date',
            'follow_up_notes'   => 'nullable|string',
        ]);

        $intervention->update($request->only([
            'intervention_type', 'intervention_date', 'description',
            'outcome', 'follow_up_date', 'follow_up_notes'
        ]));

        if ($request->outcome === 'resolved') {
            $intervention->referral->update(['status' => 'resolved', 'resolved_at' => now()]);
        }

        return redirect()->route('counselor.interventions.show', $intervention->id)
            ->with('success', 'Intervention details updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Intervention $intervention)
    {
        if ($intervention->counselor_id !== auth()->id()) {
            abort(403);
        }

        $intervention->delete();

        return redirect()->route('counselor.interventions.index')
            ->with('success', 'Intervention log deleted.');
    }
}

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
        $query = $this->filteredQuery($request);

        $interventions = $query->paginate(10)->appends($request->query());

        // Stats
        $totalInterventions = Intervention::where('counselor_id', auth()->id())->count();
        $improvingCount = Intervention::where('counselor_id', auth()->id())->where('outcome', 'improving')->count();
        $resolvedCount = Intervention::where('counselor_id', auth()->id())->where('outcome', 'resolved')->count();
        $overdueCount = Intervention::where('counselor_id', auth()->id())->overdueFollowUp()->count();
        $interventionTypes = Intervention::TYPES;

        // For the "Log New Intervention" quick-create modal on this page
        // (mirrors Referral's index page, which has the same inline modal
        // alongside its own standalone create() page).
        $referrals = $this->referralOptions();

        return view('counselor.interventions.index', compact(
            'interventions', 'totalInterventions', 'improvingCount', 'resolvedCount', 'overdueCount', 'interventionTypes', 'referrals'
        ));
    }

    /**
     * Referrals eligible to receive a new intervention — assigned to this
     * counselor or unclaimed, and not yet fully resolved. Shared by index()
     * (for the quick-create modal) and create() (the standalone page) so
     * they can't quietly drift apart into offering a different list.
     */
    private function referralOptions()
    {
        return Referral::with('student')
            ->whereIn('status', ['pending', 'in_progress'])
            ->orderBy('created_at', 'desc')
            ->get();
    }

    /**
     * The counselor's own interventions, with every index filter applied.
     * Shared by index() and export() so a filter added to one can't be
     * forgotten on the other — export should always match what's on screen.
     */
    private function filteredQuery(Request $request)
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

        // Filter by intervention type
        if ($request->filled('intervention_type')) {
            $query->where('intervention_type', $request->intervention_type);
        }

        // Filter by date range (of the intervention session itself, not when it was logged)
        if ($request->filled('date_range')) {
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate('intervention_date', today());
                    break;
                case 'this_week':
                    $query->whereBetween('intervention_date', [now()->startOfWeek(), now()->endOfWeek()]);
                    break;
                case 'this_month':
                    $query->whereMonth('intervention_date', now()->month)->whereYear('intervention_date', now()->year);
                    break;
                case 'last_month':
                    $query->whereMonth('intervention_date', now()->subMonth()->month)->whereYear('intervention_date', now()->subMonth()->year);
                    break;
            }
        }

        if ($request->boolean('overdue')) {
            $query->overdueFollowUp();
        }

        return $query;
    }

    /**
     * A scheduled-follow-up agenda: overdue, due today, and upcoming (next
     * 30 days) — grouped so a counselor can plan a week without scanning
     * the whole log for whichever rows happen to have a follow-up date.
     */
    public function followUps()
    {
        $base = Intervention::with(['referral.student'])
            ->where('counselor_id', auth()->id())
            ->whereNotNull('follow_up_date')
            ->where(function ($q) {
                $q->whereNull('outcome')->orWhere('outcome', '!=', 'resolved');
            });

        // Only Overdue is paginated — Due Today and Upcoming are already
        // self-limiting (a single day, and a fixed 30-day window), but a
        // counselor who falls behind could otherwise end up with an
        // unbounded Overdue list.
        $overdue = (clone $base)
            ->where('follow_up_date', '<', now()->startOfDay())
            ->orderBy('follow_up_date')
            ->paginate(4, ['*'], 'overdue_page');

        $dueToday = (clone $base)
            ->whereDate('follow_up_date', today())
            ->get();

        $upcoming = (clone $base)
            ->where('follow_up_date', '>', today())
            ->where('follow_up_date', '<=', now()->addDays(30))
            ->orderBy('follow_up_date')
            ->get();

        return view('counselor.interventions.followups', compact('overdue', 'dueToday', 'upcoming'));
    }

    /**
     * CSV export of the (filtered) intervention log — mirrors the pattern
     * already used for Referrals and Behavioral Reports.
     */
    public function export(Request $request)
    {
        // A bulk "Export Selected" from the index page overrides the normal
        // filters entirely — but still scoped to this counselor's own
        // records, so a tampered ids[] can't pull someone else's data.
        if ($request->filled('ids')) {
            $interventions = Intervention::with(['referral.student'])
                ->where('counselor_id', auth()->id())
                ->whereIn('id', $request->input('ids'))
                ->latest('intervention_date')
                ->get();
        } else {
            $interventions = $this->filteredQuery($request)->get();
        }

        $filename = 'interventions_export_' . date('Y-m-d_H-i') . '.csv';
        $headers = [
            'Content-type'        => 'text/csv',
            'Content-Disposition' => "attachment; filename=$filename",
            'Pragma'              => 'no-cache',
            'Cache-Control'       => 'must-revalidate, post-check=0, pre-check=0',
            'Expires'             => '0',
        ];

        $columns = ['ID', 'Date', 'Student', 'Student ID', 'Referral #', 'Intervention Type', 'Outcome', 'Follow-up Date', 'Description'];

        $callback = function () use ($interventions, $columns) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns);

            foreach ($interventions as $intervention) {
                $student = $intervention->referral->student ?? null;

                fputcsv($file, [
                    $intervention->id,
                    $intervention->intervention_date->format('Y-m-d'),
                    $student ? $student->last_name . ', ' . $student->first_name : 'N/A',
                    $student->student_id_number ?? 'N/A',
                    str_pad($intervention->referral_id, 4, '0', STR_PAD_LEFT),
                    $intervention->intervention_type,
                    $intervention->outcome ? ucfirst(str_replace('_', ' ', $intervention->outcome)) : 'Not evaluated',
                    $intervention->follow_up_date?->format('Y-m-d') ?? '',
                    $intervention->description,
                ]);
            }

            fclose($file);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $referrals = $this->referralOptions();
        $interventionTypes = Intervention::TYPES;

        return view('counselor.interventions.create', compact('referrals', 'interventionTypes'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request, \App\Services\SmsService $smsService, \App\Services\ReferralService $referralService)
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

        // Auto-update referral status if needed — through the shared service
        // so the filing teacher gets notified the same way a manual status
        // change would (this used to update the referral directly, which
        // silently skipped that notification).
        $referral = Referral::find($request->referral_id);
        if ($referral->status === 'pending') {
            $referral = $referralService->updateStatus($referral, 'in_progress', auth()->id(), $referral->counselor_notes);
        }
        if ($request->outcome === 'resolved') {
            $referral = $referralService->updateStatus($referral, 'resolved', null, $referral->counselor_notes);
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
     * Printable single-session report — for a parent meeting or a
     * disciplinary file. Viewable by any counselor, matching show()'s own
     * caseload-is-shared reasoning.
     */
    public function print(Intervention $intervention)
    {
        $intervention->load(['referral.student', 'counselor']);

        return view('counselor.interventions.print', compact('intervention'));
    }

    /**
     * Display the specified resource.
     */
    public function show(Intervention $intervention)
    {
        // Viewable by any counselor, not just the one who logged it — referrals
        // are a shared caseload (a case can be handed off, or unclaimed
        // referrals picked up by anyone), and the Referral Details page's own
        // timeline links here for whoever is looking at that referral.
        // Editing and deleting stay restricted to the logging counselor (see
        // edit()/destroy() below) since these are that counselor's own
        // session notes.
        $intervention->load('referral.student', 'counselor');

        $riskTrend = $this->riskTrendFor($intervention);

        // Everything else this student has been through, across ALL their
        // referrals — not just this one. Only this specific referral's
        // interventions were ever visible before, so a recurring pattern
        // across separate incidents was invisible unless a counselor
        // happened to check each referral individually.
        $otherInterventions = Intervention::with(['referral', 'counselor'])
            ->whereHas('referral', function ($q) use ($intervention) {
                $q->where('student_id', $intervention->referral->student_id);
            })
            ->where('id', '!=', $intervention->id)
            ->latest('intervention_date')
            ->get();

        $interventionTypes = Intervention::TYPES;

        return view('counselor.interventions.show', compact('intervention', 'riskTrend', 'otherInterventions', 'interventionTypes'));
    }

    /**
     * Whether the student's risk score has actually moved since the
     * referral this intervention belongs to was assessed — the system is
     * built around an ML risk score, but nothing previously answered "did
     * this intervention work?" Compares the score AT THE TIME of the
     * referral (a frozen historical snapshot — see RiskAssessment) against
     * the student's current latest assessment, which may come from a later
     * referral/reassessment entirely.
     *
     * @return array{status: string, referral_score: float, latest_score?: float, latest_assessed_at?: \Carbon\Carbon}|null
     */
    private function riskTrendFor(Intervention $intervention): ?array
    {
        $referralAssessment = $intervention->referral->riskAssessment;

        if (! $referralAssessment) {
            return null;
        }

        $latestAssessment = $intervention->referral->student->latestRiskAssessment;

        if (! $latestAssessment || $latestAssessment->id === $referralAssessment->id) {
            return [
                'status'         => 'no_new_assessment',
                'referral_score' => $referralAssessment->risk_score,
            ];
        }

        $diff = $latestAssessment->risk_score - $referralAssessment->risk_score;

        return [
            'status'             => $diff < 0 ? 'improved' : ($diff > 0 ? 'worsened' : 'unchanged'),
            'referral_score'     => $referralAssessment->risk_score,
            'latest_score'       => $latestAssessment->risk_score,
            'latest_assessed_at' => $latestAssessment->assessed_at,
        ];
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

        $interventionTypes = Intervention::TYPES;

        return view('counselor.interventions.edit', compact('intervention', 'interventionTypes'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Intervention $intervention, \App\Services\ReferralService $referralService)
    {
        if ($intervention->counselor_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'intervention_type' => 'required|string|max:150',
            'intervention_date' => 'required|date',
            'description'       => 'required|string',
            'outcome'           => 'nullable|in:improving,no_change,worsening,resolved',
            'follow_up_date'    => 'nullable|date|after_or_equal:intervention_date',
            'follow_up_notes'   => 'nullable|string',
        ]);

        $intervention->update($request->only([
            'intervention_type', 'intervention_date', 'description',
            'outcome', 'follow_up_date', 'follow_up_notes'
        ]));

        if ($request->outcome === 'resolved') {
            $referral = $intervention->referral;
            $referralService->updateStatus($referral, 'resolved', null, $referral->counselor_notes);
        }

        return redirect()->route('counselor.interventions.show', $intervention->id)
            ->with('success', 'Intervention details updated successfully.');
    }

    /**
     * A lightweight version of update() for the Details page's inline form —
     * just outcome and follow-up date, the two fields a counselor actually
     * needs to touch on a routine check-in, without navigating to the full
     * Edit page just to re-save the type/date/description too.
     */
    public function quickUpdate(Request $request, Intervention $intervention, \App\Services\ReferralService $referralService)
    {
        if ($intervention->counselor_id !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'outcome'        => 'nullable|in:improving,no_change,worsening,resolved',
            'follow_up_date' => 'nullable|date',
        ]);

        $oldFollowUpDate = $intervention->follow_up_date?->format('Y-m-d');

        $intervention->update([
            'outcome'        => $request->outcome,
            'follow_up_date' => $request->follow_up_date,
        ]);

        // A rescheduled follow-up needs its own reminder — otherwise the
        // one-shot follow_up_notified_at gate from the OLD date would
        // silently suppress the reminder for the new one forever.
        if ($oldFollowUpDate !== $request->follow_up_date) {
            $intervention->update(['follow_up_notified_at' => null]);
        }

        if ($request->outcome === 'resolved') {
            $referral = $intervention->referral;
            $referralService->updateStatus($referral, 'resolved', null, $referral->counselor_notes);
        }

        return redirect()->route('counselor.interventions.show', $intervention->id)
            ->with('success', 'Outcome updated successfully.');
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

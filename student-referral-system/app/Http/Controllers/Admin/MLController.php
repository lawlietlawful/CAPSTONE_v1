<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Referral;
use App\Services\RiskAssessmentService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class MLController extends Controller
{
    /**
     * Blocks the in-app retraining actions unless explicitly re-enabled.
     *
     * Both retrain paths silently degrade the validated model — the DB path
     * trains on `priority`, which the model itself set (a feedback loop), and
     * the CSV path replaces the 3,000-row model with an unvalidated upload.
     * Retraining is an offline, reviewed process (see ml_engine/train_model.py).
     * Returns a redirect to abort on, or null when retraining is allowed.
     */
    private function retrainBlocked(): ?\Illuminate\Http\RedirectResponse
    {
        if (config('services.ml.retrain_enabled')) {
            return null;
        }

        return redirect()->back()->with('error',
            'In-app retraining is disabled to protect the validated AI model. '
            . 'Retraining is done offline and reviewed (ml_engine/train_model.py), '
            . 'not from the live database.'
        );
    }

    /**
     * Retrain the ML Engine using historical data from the database.
     */
    public function retrain()
    {
        if ($blocked = $this->retrainBlocked()) {
            return $blocked;
        }

        // 1. Fetch all completed/resolved referrals to use as training data.
        // In a real system, you'd use a mix of resolved referrals and historical student data.
        $referrals = Referral::with('student', 'riskAssessment')
            ->whereNotNull('risk_assessment_id') // only ones that were assessed
            ->get();

        if ($referrals->count() < 10) {
            return redirect()->back()->with('error', 'Not enough historical data to retrain the AI. Need at least 10 records.');
        }

        $trainingRecords = [];

        foreach ($referrals as $ref) {
            $student = $ref->student;
            $riskAssessment = $ref->riskAssessment;
            
            if (!$student || !$riskAssessment) {
                continue;
            }

            // We use the actual risk level determined by the system/counselor.
            // If the referral was resolved quickly, it might be low. If it escalated, high.
            // For capstone simplicity, we just train it to recognize the historical pattern that led to this referral's priority.
            
            $risk_level = 'low';
            if ($ref->priority === 'high') {
                $risk_level = 'high';
            } elseif ($ref->priority === 'moderate') {
                $risk_level = 'moderate';
            }

            $trainingRecords[] = [
                'previous_referrals_count' => $riskAssessment->previous_referrals_count ?? 0,
                'behavioral_reports_count' => $riskAssessment->behavioral_reports_count ?? 0,
                'concern_type_encoded' => $riskAssessment->concern_type_encoded ?? 0,
                'days_since_last_referral' => $riskAssessment->days_since_last_referral ?? 999,
                'referral_reason' => $ref->reason ?? '',
                'risk_level' => $risk_level
            ];
        }

        // 2. Send the payload to Python FastAPI
        try {
            $response = Http::withHeaders(RiskAssessmentService::mlHeaders())
                ->timeout(30)
                ->post(config('services.ml.url') . '/retrain', [
                    'records' => $trainingRecords
                ]);

            if ($response->successful()) {
                return redirect()->back()->with('success', 'AI Retraining initiated successfully! The model is learning in the background.');
            } else {
                Log::error("Retrain API Error: " . $response->body());
                return redirect()->back()->with('error', 'Failed to communicate with the ML Engine.');
            }
        } catch (\Exception $e) {
            Log::error("Retrain API Connection Failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Could not connect to the ML Engine.');
        }
    }

    /**
     * Upload a CSV of historical data and send directly to Python.
     */
    public function uploadCsv(Request $request)
    {
        if ($blocked = $this->retrainBlocked()) {
            return $blocked;
        }

        $request->validate([
            'csv_file' => 'required|file|mimes:csv,txt|max:5120'
        ]);

        $file = $request->file('csv_file');
        
        $trainingRecords = [];
        $header = null;
        
        if (($handle = fopen($file->getRealPath(), 'r')) !== false) {
            while (($row = fgetcsv($handle, 1000, ',')) !== false) {
                if (!$header) {
                    $header = $row;
                    continue;
                }
                
                // Ensure we have correct column count (min 6 for the 6 required features)
                if (count($row) < 6) {
                    continue;
                }

                $data = array_combine($header, $row);
                
                if (!$data) continue;

                $trainingRecords[] = [
                    'previous_referrals_count' => (int) ($data['previous_referrals_count'] ?? 0),
                    'behavioral_reports_count' => (int) ($data['behavioral_reports_count'] ?? 0),
                    'concern_type_encoded' => (int) ($data['concern_type_encoded'] ?? 0),
                    'days_since_last_referral' => (int) ($data['days_since_last_referral'] ?? 999),
                    'referral_reason' => $data['referral_reason'] ?? '',
                    'risk_level' => strtolower($data['risk_level'] ?? 'low')
                ];
            }
            fclose($handle);
        }

        if (count($trainingRecords) < 10) {
            return redirect()->back()->with('error', 'The CSV must contain at least 10 valid records.');
        }

        try {
            $response = Http::withHeaders(RiskAssessmentService::mlHeaders())
                ->timeout(60)
                ->post(config('services.ml.url') . '/retrain', [
                    'records' => $trainingRecords
                ]);

            if ($response->successful()) {
                return redirect()->back()->with('success', 'CSV Uploaded! AI Retraining initiated with ' . count($trainingRecords) . ' records.');
            } else {
                Log::error("Retrain API Error: " . $response->body());
                return redirect()->back()->with('error', 'Failed to communicate with the ML Engine.');
            }
        } catch (\Exception $e) {
            Log::error("Retrain API Connection Failed: " . $e->getMessage());
            return redirect()->back()->with('error', 'Could not connect to the ML Engine.');
        }
    }
}

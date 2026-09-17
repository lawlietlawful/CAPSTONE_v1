<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
use App\Models\RiskAssessment;
use App\Http\Requests\StoreStudentRequest;
use App\Http\Requests\UpdateStudentRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class StudentController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $search = $request->get('search');
        $course = $request->get('course');
        $grade_level = $request->get('grade_level');
        $educationLevel = $request->get('education_level');
        $status = $request->get('status');
        // 'at_risk' means the STUDENT'S LATEST assessment is high/moderate —
        // see $atRiskStudentIds below for why "latest only" matters.
        $riskFilter = $request->get('risk_level');
        $hasReferrals = $request->boolean('has_referrals');

        // Get distinct values for dropdowns
        $courses = Student::select('course')->distinct()->whereNotNull('course')->pluck('course');

        // Canonical course/section catalog for the Add/Edit student modals
        $courseCombos = Course::picklist();

        // Students whose LATEST risk assessment is high/moderate — not
        // "has ever had one". A student who was high-risk months ago but
        // has since improved to 'low' has a low-risk latest assessment and
        // should not still show up as at-risk everywhere. Mirrors the same
        // rule already used on the Counselor dashboard's Watchlist and Risk
        // Distribution. Backs both the At-Risk Students count below and the
        // ?risk_level=at_risk filter.
        $latestRiskIds = DB::table('risk_assessments')
            ->select(DB::raw('MAX(id) as id'))
            ->groupBy('student_id')
            ->pluck('id');

        $atRiskStudentIds = RiskAssessment::whereIn('id', $latestRiskIds)
            ->whereIn('risk_level', ['high', 'moderate'])
            ->pluck('student_id');

        $students = Student::query()
            // Deleting a student cascades to permanently erase every referral,
            // behavioral report, and risk assessment on file for them (see
            // the FKs on those tables). These counts let the delete
            // confirmation say so instead of a generic "are you sure?".
            ->withCount(['referrals', 'behavioralReports', 'riskAssessments'])
            // So the at-risk filter's results can show WHY each student is
            // flagged, not just that they matched.
            ->with('latestRiskAssessment')
            ->when($search, function ($query, $search) {
                $query->where(function($q) use ($search) {
                    $q->where('student_id_number', 'like', "%{$search}%")
                      ->orWhere('first_name', 'like', "%{$search}%")
                      ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->when($course, function ($query, $course) {
                $query->where('course', $course);
            })
            ->when($grade_level, function ($query, $grade_level) {
                $query->where('grade_level', $grade_level);
            })
            ->when($educationLevel, function ($query, $educationLevel) {
                $query->where('education_level', $educationLevel);
            })
            ->when($status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($riskFilter === 'at_risk', function ($query) use ($atRiskStudentIds) {
                $query->whereIn('id', $atRiskStudentIds);
            })
            ->when($hasReferrals, function ($query) {
                $query->has('referrals');
            })
            ->latest()
            ->paginate(10);

        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'Active')->count();
        $studentsWithReferrals = Student::has('referrals')->count();
        $atRiskStudents = $atRiskStudentIds->count();

        return view('admin.students.index', compact('students', 'search', 'courses', 'courseCombos', 'course', 'grade_level', 'educationLevel', 'status', 'riskFilter', 'hasReferrals', 'totalStudents', 'activeStudents', 'studentsWithReferrals', 'atRiskStudents'))
            ->with($this->gradeLevelLists());
    }

    /**
     * The fixed Basic Education / College grade-level and strand lists,
     * shared by every student form (create, edit, per-row edit modals).
     */
    private function gradeLevelLists(): array
    {
        return [
            'collegeYearLevels' => Course::COLLEGE_YEAR_LEVELS,
            'basicEdGradeLevels' => Course::BASIC_ED_GRADE_LEVELS,
            'strands' => Course::STRANDS,
            'seniorHighGrades' => Course::SENIOR_HIGH_GRADES,
        ];
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $courseCombos = Course::picklist();

        return view('admin.students.create', compact('courseCombos'))
            ->with($this->gradeLevelLists());
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreStudentRequest $request)
    {
        try {
            DB::beginTransaction();

            $user = null;

            // Create the login account, but locked: password is an unusable
            // random placeholder and a one-time activation code (stored
            // hashed) until the student activates their own account (Student
            // ID + code) and sets their own password via the Student Portal
            // app. See AuthController::activate().
            $plainCode = User::generateActivationCode();

            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'username' => $request->student_id_number,
                'email' => null, // Email is nullable now
                'password' => Hash::make(Str::random(40)),
                'role' => 'student',
                'account_activated_at' => null,
                'activation_code' => Hash::make(User::canonicalActivationCode($plainCode)),
            ]);

            $student = Student::create([
                'user_id' => $user->id,
                'student_id_number' => $request->student_id_number,
                'course' => $request->course,
                'education_level' => $request->education_level,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'grade_level' => $request->grade_level,
                'strand' => $request->strand,
                'section' => $request->section,
                'school_year' => $request->school_year,
                'parent_name' => $request->parent_name,
                'parent_contact' => $request->parent_contact,
                'parent_email' => $request->parent_email,
                'address' => $request->address,
                'status' => $request->status,
            ]);

            DB::commit();

            return redirect()->route('admin.students.index')
                ->with('success', 'Student created. Share the activation code below so they can set up their Student Portal account.')
                ->with('activation_code', [
                    'name' => $user->name,
                    'school_id' => $student->student_id_number,
                    'code' => $plainCode,
                    'id_label' => 'Student ID',
                    'portal' => 'the Student Portal app',
                ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error creating student: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * The CSV columns accepted by the bulk import, in order. Mirrors the
     * fields on the single Add Student form.
     */
    private const IMPORT_COLUMNS = [
        'student_id_number', 'course', 'education_level', 'first_name', 'last_name', 'middle_name',
        'gender', 'birthdate', 'grade_level', 'strand', 'section', 'school_year',
        'parent_name', 'parent_contact', 'parent_email', 'student_contact',
        'address', 'status',
    ];

    /**
     * Download a CSV template pre-filled with the required columns and one
     * example row, so admins fill it in the exact shape the importer expects.
     */
    public function downloadImportTemplate()
    {
        $filename = 'student_import_template.csv';

        $callback = function () {
            $file = fopen('php://output', 'w');
            fputcsv($file, self::IMPORT_COLUMNS);
            fputcsv($file, [
                '2024-0001', 'BSIT', 'College', 'Juan', 'Dela Cruz', 'Santos',
                'Male', '2005-06-15', '4th Year', '', 'Block 1', '2025-2026',
                'Maria Dela Cruz', '09171234567', 'maria@example.com', '09181234567',
                '123 Sample St., Cagayan de Oro City', 'active',
            ]);
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Phase 1: Preview Import
     * Parses the CSV, validates it, and caches the result.
     * Returns a summary to the frontend.
     */
    public function previewImport(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = fgetcsv($handle, escape: '\\');

        if (! $header) {
            fclose($handle);
            return response()->json(['error' => 'The uploaded file appears to be empty.'], 400);
        }

        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);
        
        // Ensure all required columns are present in the header
        $missing = array_diff(self::IMPORT_COLUMNS, $header);
        if (!empty($missing)) {
            fclose($handle);
            return response()->json(['error' => 'Missing required columns: ' . implode(', ', $missing)], 400);
        }

        $catalogCombos = Course::picklist()
            ->map(fn ($c) => strtolower($c['course'] . '|' . $c['grade_level'] . '|' . $c['section']))
            ->all();

        $existingIds = Student::pluck('student_id_number')->map(fn($id) => strtolower($id))->toArray();

        $validRows = [];
        $invalidRows = [];
        $duplicateRows = [];
        $seenIds = [];
        
        $rowNumber = 1;

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $rowNumber++;
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) continue;

            $data = array_combine(
                $header,
                array_pad(array_map(fn ($v) => trim((string) $v), $row), count($header), '')
            );

            $data['school_year'] = $data['school_year'] ?: '2025-2026';
            $data['status'] = $data['status'] ?: 'active';
            $data['education_level'] = $data['education_level'] ?: 'College';
            
            $isDuplicateInDb = !empty($data['student_id_number']) && in_array(strtolower($data['student_id_number']), $existingIds, true);
            $isDuplicateInFile = !empty($data['student_id_number']) && in_array(strtolower($data['student_id_number']), $seenIds, true);
            
            $validator = Validator::make($data, [
                'student_id_number' => ['required', 'string', 'max:50'],
                'course' => ['required', 'string', 'max:100'],
                'education_level' => ['required', 'string', 'in:Basic Education,College'],
                'first_name' => ['required', 'string', 'max:100'],
                'last_name' => ['required', 'string', 'max:100'],
                'middle_name' => ['nullable', 'string', 'max:100'],
                'gender' => ['required', 'string', 'in:Male,Female'],
                'birthdate' => ['required', 'date'],
                'grade_level' => ['required', 'string', 'max:50'],
                'strand' => ['nullable', 'string', 'max:50'],
                'section' => ['required', 'string', 'max:50'],
                'school_year' => ['required', 'string', 'max:50'],
                'parent_name' => ['required', 'string', 'max:150'],
                'parent_contact' => ['required', 'string', 'max:50'],
                'parent_email' => ['nullable', 'email', 'max:150'],
                'student_contact' => ['nullable', 'string', 'max:50'],
                'address' => ['required', 'string'],
                'status' => ['required', 'in:active,inactive,transferred,graduated'],
            ]);
            
            $errors = $validator->fails() ? $validator->errors()->all() : [];
            
            if ($isDuplicateInFile) {
                $errors[] = "Student ID \"{$data['student_id_number']}\" is duplicated earlier in this file.";
            }
            
            if (! empty($data['course']) && ! empty($data['grade_level']) && ! empty($data['section'])) {
                $combo = strtolower($data['course'] . '|' . $data['grade_level'] . '|' . $data['section']);
                if (! in_array($combo, $catalogCombos, true)) {
                    $errors[] = "Course/Year/Section \"{$data['course']} / {$data['grade_level']} / {$data['section']}\" was not found in the catalog.";
                }
            }
            
            $data['_row'] = $rowNumber;
            
            if (!empty($errors)) {
                $data['_errors'] = $errors;
                $invalidRows[] = $data;
            } else if ($isDuplicateInDb) {
                $duplicateRows[] = $data;
            } else {
                $validRows[] = $data;
            }
            
            if (!empty($data['student_id_number'])) {
                $seenIds[] = strtolower($data['student_id_number']);
            }
        }
        
        fclose($handle);
        
        $importId = (string) Str::uuid();
        \Illuminate\Support\Facades\Cache::put("import_{$importId}", [
            'valid' => $validRows,
            'duplicate' => $duplicateRows,
            'invalid' => $invalidRows,
            'header' => $header
        ], now()->addHours(2));
        
        return response()->json([
            'import_id' => $importId,
            'summary' => [
                'valid' => count($validRows),
                'duplicate' => count($duplicateRows),
                'invalid' => count($invalidRows),
                'total' => count($validRows) + count($duplicateRows) + count($invalidRows)
            ],
            'invalid_preview' => array_slice($invalidRows, 0, 3)
        ]);
    }

    /**
     * Phase 2 & 4: Commit Import (Chunked)
     */
    public function commitImport(Request $request)
    {
        $request->validate([
            'import_id' => 'required|string',
            'duplicate_strategy' => 'required|in:skip,update',
            'page' => 'required|integer|min:1'
        ]);

        $cacheKey = "import_{$request->import_id}";
        $cachedData = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$cachedData) {
            return response()->json(['error' => 'Import session expired or invalid.'], 400);
        }

        $validRows = $cachedData['valid'];
        $duplicateRows = $request->duplicate_strategy === 'update' ? $cachedData['duplicate'] : [];
        
        // Combine all rows to process
        $allToProcess = array_merge($validRows, $duplicateRows);
        
        $perPage = 50; // Process 50 rows per chunk
        $totalRows = count($allToProcess);
        $totalPages = ceil($totalRows / $perPage);
        $page = $request->page;
        
        $chunk = array_slice($allToProcess, ($page - 1) * $perPage, $perPage);

        // Codes generated for newly-created accounts in this chunk, so the
        // admin can download them once the whole import finishes (mirrors
        // the error-report download). Plaintext only lives here — the DB
        // only ever stores the hash, same as the single-student flow.
        $generatedCodes = [];

        foreach ($chunk as $data) {
            try {
                DB::transaction(function () use ($data, &$generatedCodes) {
                    $student = Student::where('student_id_number', $data['student_id_number'])->first();

                    if ($student) {
                        // Update existing
                        $user = $student->user;
                        $user->update([
                            'name' => $data['first_name'] . ' ' . $data['last_name'],
                        ]);
                        
                        $student->update([
                            'course' => $data['course'],
                            'education_level' => $data['education_level'],
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'middle_name' => $data['middle_name'] ?: null,
                            'gender' => $data['gender'],
                            'birthdate' => $data['birthdate'],
                            'grade_level' => $data['grade_level'],
                            'strand' => $data['strand'] ?: null,
                            'section' => $data['section'],
                            'school_year' => $data['school_year'],
                            'parent_name' => $data['parent_name'],
                            'parent_contact' => $data['parent_contact'],
                            'parent_email' => $data['parent_email'] ?: null,
                            'student_contact' => $data['student_contact'] ?: null,
                            'address' => $data['address'],
                            'status' => $data['status'],
                        ]);
                    } else {
                        // Create new
                        $plainCode = User::generateActivationCode();

                        $user = User::create([
                            'name' => $data['first_name'] . ' ' . $data['last_name'],
                            'username' => $data['student_id_number'],
                            'email' => null,
                            'password' => Hash::make(Str::random(40)),
                            'role' => 'student',
                            'account_activated_at' => null,
                            'activation_code' => Hash::make(User::canonicalActivationCode($plainCode)),
                        ]);

                        $generatedCodes[] = [
                            'student_id_number' => $data['student_id_number'],
                            'name' => $user->name,
                            'activation_code' => $plainCode,
                        ];

                        Student::create([
                            'user_id' => $user->id,
                            'student_id_number' => $data['student_id_number'],
                            'course' => $data['course'],
                            'education_level' => $data['education_level'],
                            'first_name' => $data['first_name'],
                            'last_name' => $data['last_name'],
                            'middle_name' => $data['middle_name'] ?: null,
                            'gender' => $data['gender'],
                            'birthdate' => $data['birthdate'],
                            'grade_level' => $data['grade_level'],
                            'strand' => $data['strand'] ?: null,
                            'section' => $data['section'],
                            'school_year' => $data['school_year'],
                            'parent_name' => $data['parent_name'],
                            'parent_contact' => $data['parent_contact'],
                            'parent_email' => $data['parent_email'] ?: null,
                            'student_contact' => $data['student_contact'] ?: null,
                            'address' => $data['address'],
                            'status' => $data['status'],
                        ]);
                    }
                });
            } catch (\Exception $e) {
                // Silently log or ignore chunk errors for now to not break the batch
                \Log::error('Import error for row ' . $data['_row'] . ': ' . $e->getMessage());
            }
        }

        if (!empty($generatedCodes)) {
            $cachedData['codes'] = array_merge($cachedData['codes'] ?? [], $generatedCodes);
            \Illuminate\Support\Facades\Cache::put($cacheKey, $cachedData, now()->addHours(2));
        }

        // If this is the last page, we could clean up the cache, but let's keep it for downloading errors

        return response()->json([
            'success' => true,
            'current_page' => $page,
            'total_pages' => max(1, $totalPages),
            'progress' => $totalPages > 0 ? round(($page / $totalPages) * 100) : 100,
            'codes_generated' => count($cachedData['codes'] ?? []),
        ]);
    }

    /**
     * Phase 3: Download Error Report
     */
    public function downloadImportErrors($importId)
    {
        $cacheKey = "import_{$importId}";
        $cachedData = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$cachedData || empty($cachedData['invalid'])) {
            return redirect()->route('admin.students.index')->with('error', 'No errors found or session expired.');
        }

        $filename = "import_errors_{$importId}.csv";
        $header = $cachedData['header'];
        $header[] = 'error_reason'; // Append error reason column

        $callback = function () use ($cachedData, $header) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $header);
            
            foreach ($cachedData['invalid'] as $invalidRow) {
                $row = [];
                // Fill original columns
                foreach ($cachedData['header'] as $col) {
                    $row[] = $invalidRow[$col] ?? '';
                }
                // Append errors
                $row[] = implode(" | ", $invalidRow['_errors']);
                fputcsv($file, $row);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Download the one-time activation codes generated for newly-created
     * accounts in this import. Codes only ever exist in plaintext here (the
     * cache) and in the CSV the admin downloads — the DB stores only the
     * hash, so this is the only way to retrieve them after the fact.
     */
    public function downloadImportCodes($importId)
    {
        $cacheKey = "import_{$importId}";
        $cachedData = \Illuminate\Support\Facades\Cache::get($cacheKey);

        if (!$cachedData || empty($cachedData['codes'])) {
            return redirect()->route('admin.students.index')->with('error', 'No activation codes found or session expired.');
        }

        $filename = "import_activation_codes_{$importId}.csv";

        $callback = function () use ($cachedData) {
            $file = fopen('php://output', 'w');
            fputcsv($file, ['student_id_number', 'name', 'activation_code']);

            foreach ($cachedData['codes'] as $row) {
                fputcsv($file, [$row['student_id_number'], $row['name'], $row['activation_code']]);
            }
            fclose($file);
        };

        return response()->stream($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Issue a fresh activation code for a student who hasn't activated yet
     * (e.g. they lost the original). Codes are one-time and stored hashed,
     * so this is the only way to recover one. Mirrors
     * UserController::regenerateActivationCode() for teachers.
     */
    public function regenerateActivationCode(Student $student)
    {
        $user = $student->user;

        if (!$user) {
            return back()->with('error', 'This student has no linked account.');
        }
        if ($user->isActivated()) {
            return back()->with('error', "{$student->full_name}'s account is already activated.");
        }

        $plainCode = User::generateActivationCode();
        $user->update(['activation_code' => Hash::make(User::canonicalActivationCode($plainCode))]);

        return back()
            ->with('success', 'New activation code generated.')
            ->with('activation_code', [
                'name' => $user->name,
                'school_id' => $student->student_id_number,
                'code' => $plainCode,
                'id_label' => 'Student ID',
                'portal' => 'the Student Portal app',
            ]);
    }

    /**
     * Display the specified resource.
     */
    public function show(Student $student)
    {
        // Eager load relationships for the student profile view
        $student->load(['behavioralReports', 'riskAssessments', 'referrals', 'user']);
        
        $timeline = collect();

        foreach ($student->referrals as $referral) {
            $timeline->push([
                'type' => 'referral',
                'title' => 'Referral: ' . $referral->referral_type_label,
                'description' => $referral->reason,
                'status' => $referral->status,
                'date' => $referral->created_at,
                'icon' => 'ti-file-description',
                'color' => 'blue'
            ]);
        }

        foreach ($student->behavioralReports as $report) {
            $timeline->push([
                'type' => 'behavioral',
                'title' => 'Incident Report: ' . $report->incident_type,
                'description' => $report->description,
                'status' => $report->status,
                'date' => \Carbon\Carbon::parse($report->incident_date),
                'icon' => 'ti-message-report',
                'color' => 'red'
            ]);
        }
        
        foreach ($student->riskAssessments as $risk) {
            $timeline->push([
                'type' => 'risk',
                'title' => 'Risk Assessment: ' . ucfirst($risk->risk_level),
                'description' => 'Score: ' . $risk->risk_score,
                'status' => '',
                'date' => $risk->created_at,
                'icon' => 'ti-chart-pie',
                'color' => 'amber'
            ]);
        }

        $timeline = $timeline->sortByDesc('date');

        $courseCombos = Course::picklist();

        return view('admin.students.show', compact('student', 'timeline', 'courseCombos'))
            ->with($this->gradeLevelLists());
    }



    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateStudentRequest $request, Student $student)
    {
        try {
            DB::beginTransaction();

            $student->update([
                'student_id_number' => $request->student_id_number,
                'course' => $request->course,
                'education_level' => $request->education_level,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
                'middle_name' => $request->middle_name,
                'gender' => $request->gender,
                'birthdate' => $request->birthdate,
                'grade_level' => $request->grade_level,
                'strand' => $request->strand,
                'section' => $request->section,
                'school_year' => $request->school_year,
                'parent_name' => $request->parent_name,
                'parent_contact' => $request->parent_contact,
                'student_contact' => $request->student_contact,
                'parent_email' => $request->parent_email,
                'address' => $request->address,
                'status' => $request->status,
            ]);

            // Sync user account details if needed
            if ($student->user) {
                $student->user->update([
                    'name' => $request->first_name . ' ' . $request->last_name,
                    'username' => $request->student_id_number,
                ]);
            }

            DB::commit();

            return redirect()->route('admin.students.show', $student->id)
                ->with('success', 'Student updated successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error updating student: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Student $student)
    {
        try {
            DB::beginTransaction();
            
            $user = $student->user;
            
            $student->delete();
            
            if ($user) {
                $user->delete();
            }

            DB::commit();

            return redirect()->route('admin.students.index')
                ->with('success', 'Student deleted successfully.');

        } catch (\Exception $e) {
            DB::rollBack();
            return back()->with('error', 'Error deleting student: ' . $e->getMessage());
        }
    }
}

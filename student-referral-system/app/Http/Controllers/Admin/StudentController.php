<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Student;
use App\Models\User;
use App\Models\Course;
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

        // Get distinct values for dropdowns
        $courses = Student::select('course')->distinct()->whereNotNull('course')->pluck('course');

        // Canonical course/section catalog for the Add/Edit student modals
        $courseCombos = Course::picklist();

        $students = Student::query()
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
            ->latest()
            ->paginate(10);

        $totalStudents = Student::count();
        $activeStudents = Student::where('status', 'Active')->count();
        $studentsWithReferrals = Student::has('referrals')->count();
        $atRiskStudents = Student::whereHas('riskAssessments', function($q) {
            $q->whereIn('risk_level', ['high', 'moderate']);
        })->count();

        return view('admin.students.index', compact('students', 'search', 'courses', 'courseCombos', 'course', 'grade_level', 'educationLevel', 'status', 'totalStudents', 'activeStudents', 'studentsWithReferrals', 'atRiskStudents'))
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
            // random placeholder until the student activates their own
            // account (Student ID + birthdate) and sets their own password
            // via the Student Portal app. See AuthController::activate().
            $user = User::create([
                'name' => $request->first_name . ' ' . $request->last_name,
                'username' => $request->student_id_number,
                'email' => null, // Email is nullable now
                'password' => Hash::make(Str::random(40)),
                'role' => 'student',
                'account_activated_at' => null,
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
                ->with('success', "Student created. They can activate their Student Portal account using Student ID \"{$student->student_id_number}\" and their birthdate.");

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
     * Bulk-import students from an uploaded CSV. Each row is validated and
     * created independently: valid rows are saved immediately, invalid rows
     * are skipped and reported back with their row number and reason, so a
     * typo in one row never blocks the rest of the batch.
     */
    public function import(Request $request)
    {
        $request->validate([
            'csv_file' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        $handle = fopen($request->file('csv_file')->getRealPath(), 'r');
        $header = fgetcsv($handle, escape: '\\');

        if (! $header) {
            fclose($handle);
            return back()->with('error', 'The uploaded file appears to be empty.');
        }

        // Normalize header names so minor edits (extra spaces, casing) still map correctly.
        $header = array_map(fn ($h) => strtolower(trim((string) $h)), $header);

        // Catalog combos, for validating course/grade_level/section against
        // the same canonical list the single Add Student dropdown enforces.
        $catalogCombos = Course::picklist()
            ->map(fn ($c) => strtolower($c['course'] . '|' . $c['grade_level'] . '|' . $c['section']))
            ->all();

        $seenIds = [];
        $errors = [];
        $successCount = 0;
        $rowNumber = 1; // header is row 1

        while (($row = fgetcsv($handle, escape: '\\')) !== false) {
            $rowNumber++;

            // Skip fully blank lines (e.g. trailing newline in the file).
            if (count(array_filter($row, fn ($v) => trim((string) $v) !== '')) === 0) {
                continue;
            }

            $data = array_combine(
                $header,
                array_pad(array_map(fn ($v) => trim((string) $v), $row), count($header), '')
            );

            // Sensible defaults matching what the single Add Student modal hardcodes.
            $data['school_year'] = $data['school_year'] ?: '2025-2026';
            $data['status'] = $data['status'] ?: 'active';
            $data['education_level'] = $data['education_level'] ?: 'College';

            $rowErrors = $this->validateImportRow($data, $catalogCombos, $seenIds);

            if (! empty($rowErrors)) {
                $errors[] = ['row' => $rowNumber, 'messages' => $rowErrors];
                continue;
            }

            $seenIds[] = strtolower($data['student_id_number']);

            try {
                DB::transaction(function () use ($data) {
                    $user = User::create([
                        'name' => $data['first_name'] . ' ' . $data['last_name'],
                        'username' => $data['student_id_number'],
                        'email' => null,
                        'password' => Hash::make(Str::random(40)),
                        'role' => 'student',
                        'account_activated_at' => null,
                    ]);

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
                });

                $successCount++;
            } catch (\Exception $e) {
                $errors[] = ['row' => $rowNumber, 'messages' => ['Unexpected error: ' . $e->getMessage()]];
            }
        }

        fclose($handle);

        return redirect()->route('admin.students.index')->with('import_results', [
            'success_count' => $successCount,
            'errors' => $errors,
        ]);
    }

    /**
     * Validate a single import row. Returns a list of human-readable error
     * messages, empty if the row is valid.
     */
    private function validateImportRow(array $data, array $catalogCombos, array $seenIds): array
    {
        // If the Student ID is already duplicated within this same file, skip
        // the DB "unique" check for it — the in-file duplicate message below
        // is clearer and avoids reporting the same problem twice.
        $isDuplicateInFile = ! empty($data['student_id_number'])
            && in_array(strtolower($data['student_id_number']), $seenIds, true);

        $validator = Validator::make($data, [
            'student_id_number' => $isDuplicateInFile
                ? ['required', 'string', 'max:50']
                : ['required', 'string', 'max:50', 'unique:students,student_id_number'],
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

        // Duplicate Student ID within this same uploaded file.
        if (! empty($data['student_id_number']) && in_array(strtolower($data['student_id_number']), $seenIds, true)) {
            $errors[] = "Student ID \"{$data['student_id_number']}\" is duplicated earlier in this file.";
        }

        // The course/grade_level/section combo must exist in the Courses &
        // Sections catalog — the same restriction the dropdown enforces for
        // students added one at a time.
        if (! empty($data['course']) && ! empty($data['grade_level']) && ! empty($data['section'])) {
            $combo = strtolower($data['course'] . '|' . $data['grade_level'] . '|' . $data['section']);
            if (! in_array($combo, $catalogCombos, true)) {
                $errors[] = "Course/Year/Section \"{$data['course']} / {$data['grade_level']} / {$data['section']}\" was not found in the Courses & Sections catalog. Add it there first.";
            }
        }

        return $errors;
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

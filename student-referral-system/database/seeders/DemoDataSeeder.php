<?php

namespace Database\Seeders;

use App\Models\Course;
use App\Models\CourseSection;
use App\Models\Student;
use App\Models\TeacherAssignment;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Sample data for demos/testing: 5 courses (each with one "Block 1" section
 * under 1st Year), 5 teachers (one per course, advising that block), and 10
 * students (2 per course/block).
 *
 * Accounts are created LOCKED with a one-time activation code, exactly like
 * the real Add Teacher / Add Student flows (UserController::store(),
 * StudentController::store()) — not pre-activated shortcuts — so this data
 * also exercises the activation system rather than bypassing it. Codes are
 * printed to the console since, like the real flows, the DB only ever
 * stores the hash.
 */
class DemoDataSeeder extends Seeder
{
    private const COURSES = [
        ['name' => 'Bachelor of Science in Business Administration', 'level' => 'College'],
        ['name' => 'Bachelor of Science in Nursing', 'level' => 'College'],
        ['name' => 'Bachelor of Secondary Education', 'level' => 'College'],
        ['name' => 'Bachelor of Elementary Education', 'level' => 'College'],
        ['name' => 'Bachelor of Science in Hospitality Management', 'level' => 'College'],
    ];

    private const TEACHERS = [
        ['name' => 'Roberto Villanueva', 'employee_id' => 'T-2026-101'],
        ['name' => 'Cristina Bautista', 'employee_id' => 'T-2026-102'],
        ['name' => 'Manuel Aquino', 'employee_id' => 'T-2026-103'],
        ['name' => 'Josefina Ramos', 'employee_id' => 'T-2026-104'],
        ['name' => 'Ricardo Mendoza', 'employee_id' => 'T-2026-105'],
    ];

    /** [first, last, gender, birthdate] pairs, two per course in COURSES order. */
    private const STUDENTS = [
        ['Angelo', 'Fernandez', 'Male', '2006-03-14'],
        ['Bea', 'Santos', 'Female', '2006-07-22'],
        ['Carla', 'Reyes', 'Female', '2005-11-02'],
        ['Daniel', 'Cruz', 'Male', '2006-01-18'],
        ['Ella', 'Morales', 'Female', '2005-09-30'],
        ['Francis', 'Torres', 'Male', '2006-05-09'],
        ['Grace', 'Villaruel', 'Female', '2006-02-25'],
        ['Henry', 'Domingo', 'Male', '2005-12-11'],
        ['Irene', 'Castillo', 'Female', '2006-08-06'],
        ['Jomar', 'Aguilar', 'Male', '2006-04-19'],
    ];

    public function run(): void
    {
        if (Student::count() >= 10 && User::where('role', 'teacher')->count() >= 5) {
            $this->command?->warn('DemoDataSeeder: 10+ students and 5+ teachers already exist — skipping.');
            return;
        }

        $courses = $this->seedCourses();
        $this->seedTeachers($courses);
        $this->seedStudents($courses);
    }

    /** @return array<int, array{course: Course, section: CourseSection}> */
    private function seedCourses(): array
    {
        $result = [];

        foreach (self::COURSES as $data) {
            $course = Course::firstOrCreate(
                ['name' => $data['name']],
                ['education_level' => $data['level']]
            );

            $section = CourseSection::firstOrCreate([
                'course_id' => $course->id,
                'grade_level' => '1st Year',
                'section' => 'Block 1',
            ]);

            $result[] = ['course' => $course, 'section' => $section];
        }

        $this->command?->info('Seeded ' . count($result) . ' courses (1st Year, Block 1 each).');

        return $result;
    }

    private function seedTeachers(array $courses): void
    {
        $codes = [];

        foreach (self::TEACHERS as $i => $data) {
            $user = User::where('username', $data['employee_id'])->first();

            if (! $user) {
                $plainCode = User::generateActivationCode();

                $user = User::create([
                    'name' => $data['name'],
                    'username' => $data['employee_id'],
                    'email' => null,
                    'password' => Hash::make(Str::random(40)),
                    'role' => 'teacher',
                    'account_activated_at' => null,
                    'activation_code' => Hash::make(User::canonicalActivationCode($plainCode)),
                ]);

                $codes[] = ['name' => $data['name'], 'employee_id' => $data['employee_id'], 'code' => $plainCode];
            }

            $course = $courses[$i]['course'];
            $section = $courses[$i]['section'];

            TeacherAssignment::firstOrCreate([
                'teacher_id' => $user->id,
                'course' => $course->name,
                'grade_level' => $section->grade_level,
                'section' => $section->section,
            ]);
        }

        $this->command?->info('Seeded ' . count(self::TEACHERS) . ' teachers, one per course/block.');
        $this->printCodes('Teacher', $codes, 'employee_id');
    }

    private function seedStudents(array $courses): void
    {
        $codes = [];

        foreach (self::STUDENTS as $i => [$first, $last, $gender, $birthdate]) {
            $studentIdNumber = '2026-' . str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT);

            if (Student::where('student_id_number', $studentIdNumber)->exists()) {
                continue;
            }

            $courseInfo = $courses[intdiv($i, 2)];
            $plainCode = User::generateActivationCode();

            $user = User::create([
                'name' => "{$first} {$last}",
                'username' => $studentIdNumber,
                'email' => null,
                'password' => Hash::make(Str::random(40)),
                'role' => 'student',
                'account_activated_at' => null,
                'activation_code' => Hash::make(User::canonicalActivationCode($plainCode)),
            ]);

            Student::create([
                'user_id' => $user->id,
                'student_id_number' => $studentIdNumber,
                'course' => $courseInfo['course']->name,
                'education_level' => $courseInfo['course']->education_level,
                'first_name' => $first,
                'last_name' => $last,
                'gender' => $gender,
                'birthdate' => $birthdate,
                'grade_level' => $courseInfo['section']->grade_level,
                'section' => $courseInfo['section']->section,
                'school_year' => '2025-2026',
                'parent_name' => "Parent of {$first} {$last}",
                'parent_contact' => '0917' . str_pad((string) random_int(0, 9999999), 7, '0', STR_PAD_LEFT),
                'address' => 'Cagayan de Oro City, Misamis Oriental',
                'status' => 'active',
            ]);

            $codes[] = ['name' => "{$first} {$last}", 'student_id' => $studentIdNumber, 'code' => $plainCode];
        }

        $this->command?->info('Seeded ' . count(self::STUDENTS) . ' students across the 5 courses (2 each).');
        $this->printCodes('Student', $codes, 'student_id');
    }

    private function printCodes(string $label, array $codes, string $idKey): void
    {
        if (empty($codes) || ! $this->command) {
            return;
        }

        $this->command->line("\n{$label} activation codes (shown once — same as the real Add {$label} flow; regenerate from the admin UI if lost):");
        foreach ($codes as $row) {
            $this->command->line("  {$row['name']} ({$row[$idKey]}): {$row['code']}");
        }
    }
}

<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Seminar;
use Carbon\Carbon;

class SeminarSeeder extends Seeder
{
    public function run(): void
    {
        Seminar::create([
            'title' => 'Time Management & Attendance Recovery',
            'description' => 'A specialized workshop designed to help students build better daily routines, prioritize their schedule, and understand the impact of chronic absenteeism on their future careers.',
            'date' => Carbon::now()->addDays(5)->format('Y-m-d'),
            'time' => '13:00:00',
            'venue' => 'Guidance Office Room A',
            'speaker' => 'Dr. Maria Santos',
            'max_participants' => 30,
            'status' => 'upcoming',
            'is_required' => true,
            'trigger_reason' => 'attendance_intervention',
            'target_course' => null,
            'target_grade_level' => null,
        ]);

        Seminar::create([
            'title' => 'Academic Resilience & Study Habits',
            'description' => 'An intervention program for students struggling academically. Learn effective study techniques, memory retention strategies, and how to bounce back from failing grades.',
            'date' => Carbon::now()->addDays(7)->format('Y-m-d'),
            'time' => '15:00:00',
            'venue' => 'Main Library Hall',
            'speaker' => 'Prof. Juan Dela Cruz',
            'max_participants' => 25,
            'status' => 'upcoming',
            'is_required' => true,
            'trigger_reason' => 'academic_recovery',
            'target_course' => null,
            'target_grade_level' => null,
        ]);

        Seminar::create([
            'title' => 'Building Healthy Peer Relationships',
            'description' => 'A safe space seminar addressing peer conflict, bullying, and emotional intelligence. Students will learn conflict resolution and empathy.',
            'date' => Carbon::now()->addDays(10)->format('Y-m-d'),
            'time' => '09:00:00',
            'venue' => 'Student Union Center',
            'speaker' => 'Ms. Anna Reyes',
            'max_participants' => 20,
            'status' => 'upcoming',
            'is_required' => true,
            'trigger_reason' => 'anti_bullying',
            'target_course' => null,
            'target_grade_level' => null,
        ]);

        Seminar::create([
            'title' => 'Values Formation & Discipline',
            'description' => 'A core behavioral intervention focusing on institutional values, personal discipline, and respect for authority.',
            'date' => Carbon::now()->addDays(14)->format('Y-m-d'),
            'time' => '14:00:00',
            'venue' => 'Auditorium',
            'speaker' => 'Rev. Fr. Gomez',
            'max_participants' => 50,
            'status' => 'upcoming',
            'is_required' => true,
            'trigger_reason' => 'values_formation',
            'target_course' => null,
            'target_grade_level' => null,
        ]);
        
        Seminar::create([
            'title' => 'General Student Orientation Refresher',
            'description' => 'A refresher course covering school policies, available resources, and general motivation for student success.',
            'date' => Carbon::now()->addDays(20)->format('Y-m-d'),
            'time' => '10:00:00',
            'venue' => 'Online (Zoom)',
            'speaker' => 'Guidance Staff',
            'max_participants' => 100,
            'status' => 'upcoming',
            'is_required' => false,
            'trigger_reason' => 'orientation',
            'target_course' => null,
            'target_grade_level' => null,
        ]);
    }
}

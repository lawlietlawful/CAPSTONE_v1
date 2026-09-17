<?php

namespace Tests\Unit;

use App\Models\Message;
use App\Models\User;
use Tests\TestCase;

/**
 * Pure logic for Message::canInitiate() — who may start a new thread with
 * whom. No database involved; only the two users' roles matter, so these
 * are plain unit tests against unsaved model instances.
 */
class MessageAuthorizationTest extends TestCase
{
    private function withRole(string $role): User
    {
        return new User(['role' => $role]);
    }

    public function test_student_can_never_initiate(): void
    {
        $student = $this->withRole('student');

        foreach (['student', 'teacher', 'admin', 'super_admin'] as $role) {
            $this->assertFalse(
                Message::canInitiate($student, $this->withRole($role)),
                "student should not be able to initiate to {$role}"
            );
        }
    }

    public function test_teacher_can_only_initiate_to_a_counselor(): void
    {
        $teacher = $this->withRole('teacher');

        $this->assertTrue(Message::canInitiate($teacher, $this->withRole('admin')));
        $this->assertTrue(Message::canInitiate($teacher, $this->withRole('super_admin')));
        $this->assertFalse(Message::canInitiate($teacher, $this->withRole('teacher')));
        $this->assertFalse(Message::canInitiate($teacher, $this->withRole('student')));
    }

    public function test_counselor_can_initiate_to_student_or_teacher_but_not_another_counselor(): void
    {
        $counselor = $this->withRole('admin');

        $this->assertTrue(Message::canInitiate($counselor, $this->withRole('student')));
        $this->assertTrue(Message::canInitiate($counselor, $this->withRole('teacher')));
        $this->assertFalse(Message::canInitiate($counselor, $this->withRole('admin')));
        $this->assertFalse(Message::canInitiate($counselor, $this->withRole('super_admin')));
    }

    public function test_super_admin_follows_the_same_rule_as_a_counselor(): void
    {
        $superAdmin = $this->withRole('super_admin');

        $this->assertTrue(Message::canInitiate($superAdmin, $this->withRole('student')));
        $this->assertTrue(Message::canInitiate($superAdmin, $this->withRole('teacher')));
        $this->assertFalse(Message::canInitiate($superAdmin, $this->withRole('admin')));
    }
}

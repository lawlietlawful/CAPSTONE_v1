<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateStudentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the student ID being updated from the route
        $studentId = $this->route('student') ? $this->route('student')->id : null;

        return [
            'student_id_number' => ['required', 'string', 'max:50', 'unique:students,student_id_number,' . $studentId],
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
        ];
    }
}

<?php

namespace App\Http\Requests;

use App\Models\Course;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class StoreStudentRequest extends FormRequest
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
        return [
            'student_id_number' => ['required', 'string', 'max:50', 'unique:students,student_id_number'],
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
            
            // User account option (Admin can choose to generate an account)
            'create_account' => ['nullable', 'boolean'],
        ];
    }

    /**
     * The Add Student form's course/grade/section dropdowns are cascading
     * and already constrained to the catalog, so this only ever fires on a
     * raw/bypassed request — but the CSV importer enforces the exact same
     * rule on every row, and this form shouldn't be the one path that lets
     * a student point at a course/section combination that doesn't exist.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator) {
            if ($this->filled(['course', 'grade_level', 'section'])
                && ! Course::comboExists($this->course, $this->grade_level, $this->section)) {
                $validator->errors()->add(
                    'course',
                    'This Course / Year Level / Section combination was not found in the catalog.'
                );
            }
        });
    }
}

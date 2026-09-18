<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreUserRequest extends FormRequest
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
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            // Teachers log in with an Employee ID (stored in username) and activate
            // their own password, so no admin-set password. Admin/counselor are
            // web-only and get a password directly.
            'username' => ['nullable', 'string', 'max:255', 'unique:users', 'required_if:role,teacher'],
            'password' => ['nullable', 'string', 'min:8', 'required_unless:role,teacher'],
            'role' => [
                'required', 
                'string', 
                auth()->user()->role === 'super_admin' ? 'in:super_admin,admin,teacher' : 'in:teacher'
            ],
            'assignments' => ['nullable', 'array'],
            'assignments.*.course' => ['nullable', 'string', 'max:255'],
            'assignments.*.grade_level' => ['nullable', 'string', 'max:50'],
            'assignments.*.section' => ['nullable', 'string', 'max:50'],
        ];
    }
}

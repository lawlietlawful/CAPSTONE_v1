<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

/**
 * Change Password for staff (super admin and counselors). The form posts to
 * the existing password.update route (Auth\PasswordController). A staff
 * member whose password was reset to a temporary one is held here by
 * EnsurePasswordIsChanged until they pick their own.
 */
class AccountPasswordController extends Controller
{
    public function edit(): View
    {
        return view('account.password', ['forced' => (bool) auth()->user()->must_change_password]);
    }
}

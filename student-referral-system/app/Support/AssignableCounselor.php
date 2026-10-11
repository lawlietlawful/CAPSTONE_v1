<?php

namespace App\Support;

use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Exists;

/**
 * Validation for "assign this to a counselor" fields, now that an account can
 * be deactivated.
 *
 * A NEW assignment must go to an active account. A form that re-submits an
 * EXISTING assignment (a referral's update form) must still accept the
 * current assignee even if they've since been deactivated — otherwise simply
 * saving that form would fail, or worse, the dropdown would drop them and
 * silently unassign the case. Pass that current id as $keep.
 *
 * Deliberately adds no role check of its own: callers that already require
 * role=admin keep chaining ->where('role', 'admin') as before.
 */
class AssignableCounselor
{
    public static function rule(?int $keep = null): Exists
    {
        return Rule::exists('users', 'id')->where(function ($q) use ($keep) {
            $q->whereNull('deactivated_at');
            if ($keep) {
                $q->orWhere('id', $keep);
            }
        });
    }
}

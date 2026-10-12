<?php

namespace App\Support;

/**
 * Links from pages both roles share (student profile, At-Risk detail,
 * teacher profile, the case/safety badges) to a page that exists in two
 * versions: admin.* is the super admin's (unscoped, with delete/bulk
 * tools, behind the super_admin middleware) and counselor.* is the
 * counselor's (scoped, with case ownership). Pointing a counselor at the
 * admin.* version would now 403 — and used to hand them the super
 * admin's tools.
 *
 *   RoleRoute::to('referrals.show', $id)  →  admin.referrals.show for the
 *   super admin, counselor.referrals.show for a counselor.
 */
class RoleRoute
{
    public static function to(string $name, mixed $parameters = []): string
    {
        $prefix = auth()->user()?->role === 'super_admin' ? 'admin' : 'counselor';

        return route("{$prefix}.{$name}", $parameters);
    }
}

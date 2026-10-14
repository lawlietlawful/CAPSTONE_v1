<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * "Remember me" is gone from the login page. Clearing the stored tokens
 * also ends sign-ins already remembered on some browser (a cookie only
 * works while it matches the token saved here), instead of leaving them
 * valid for the rest of their ~400 days.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNotNull('remember_token')->update(['remember_token' => null]);
    }

    public function down(): void
    {
        // Nothing to restore: the old tokens are meant to stay gone.
    }
};

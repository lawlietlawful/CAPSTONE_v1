<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Account deactivation. Deleting a user is ON DELETE CASCADE for every
 * behavioral report, referral and intervention they filed, so a departed
 * teacher or counselor needs a way to be switched off that keeps the
 * students' case history intact. Null = active; a timestamp = deactivated
 * since then (and reversible — clearing it reactivates the same account).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('deactivated_at')->nullable()->after('account_activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('deactivated_at');
        });
    }
};

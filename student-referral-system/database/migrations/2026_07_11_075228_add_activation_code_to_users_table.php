<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Teachers now activate their mobile account (choose their own password) using
 * their School ID + a one-time code the admin issues — mirroring the student
 * activation flow. The code is stored HASHED (like a password) and nulled once
 * the account is activated.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('activation_code')->nullable()->after('account_activated_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('activation_code');
        });
    }
};

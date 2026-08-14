<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('account_activated_at')->nullable()->after('password');
        });

        // Grandfather in students created under the old scheme (default
        // password == student ID) so they aren't locked out by this change.
        DB::table('users')
            ->where('role', 'student')
            ->update(['account_activated_at' => DB::raw('created_at')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('account_activated_at');
        });
    }
};

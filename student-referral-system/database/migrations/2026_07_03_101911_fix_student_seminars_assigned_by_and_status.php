<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('student_seminars', function (Blueprint $table) {
            $table->enum('assigned_by', ['manual', 'ml_system'])->default('manual')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('student_seminars', function (Blueprint $table) {
            $table->dropColumn('assigned_by');
        });
    }
};

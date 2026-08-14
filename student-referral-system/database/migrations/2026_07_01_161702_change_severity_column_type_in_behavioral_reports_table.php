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
        // Alter ENUM to add Low, Medium, High, Critical
        DB::statement("ALTER TABLE behavioral_reports MODIFY COLUMN severity ENUM('minor', 'moderate', 'severe', 'Low', 'Medium', 'High', 'Critical') DEFAULT 'Low'");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement("ALTER TABLE behavioral_reports MODIFY COLUMN severity ENUM('minor', 'moderate', 'severe') DEFAULT 'minor'");
    }
};

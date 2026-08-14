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
        Schema::table('attendance', function (Blueprint $table) {
            $table->enum('absence_type', ['excused', 'unexcused'])->nullable()->after('status');
        });
        
        // Update existing absences to be unexcused by default
        DB::table('attendance')->where('status', 'absent')->update(['absence_type' => 'unexcused']);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('attendance', function (Blueprint $table) {
            $table->dropColumn('absence_type');
        });
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Existing courses were all college programs (BSIT, BSED, ...), so they
     * default to 'College' — nothing already in the catalog silently becomes
     * a Basic Education entry.
     */
    public function up(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->enum('education_level', ['Basic Education', 'College'])
                  ->default('College')
                  ->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('courses', function (Blueprint $table) {
            $table->dropColumn('education_level');
        });
    }
};

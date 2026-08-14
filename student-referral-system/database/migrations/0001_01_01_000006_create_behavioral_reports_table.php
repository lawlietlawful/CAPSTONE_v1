<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('behavioral_reports', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('reported_by')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('incident_type');
            $table->text('description');
            $table->enum('severity', [
                'minor',
                'moderate',
                'severe'
            ])->default('minor');
            $table->date('incident_date');
            $table->string('location')->nullable();
            $table->enum('status', [
                'pending',
                'reviewed',
                'resolved'
            ])->default('pending');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('behavioral_reports');
    }
};

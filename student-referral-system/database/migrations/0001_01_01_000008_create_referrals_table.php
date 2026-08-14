<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('referred_by')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->foreignId('counselor_id')
                  ->nullable()
                  ->constrained('users')
                  ->onDelete('set null');
            $table->foreignId('risk_assessment_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('set null');
            $table->string('referral_type');
            $table->text('reason');
            $table->enum('priority', [
                'low',
                'moderate',
                'high'
            ])->default('low');
            $table->enum('status', [
                'pending',
                'in_progress',
                'resolved',
                'cancelled'
            ])->default('pending');
            $table->text('counselor_notes')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
    }
};

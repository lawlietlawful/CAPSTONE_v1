<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sms_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('set null');
            $table->foreignId('student_id')
                  ->nullable()
                  ->constrained()
                  ->onDelete('set null');
            $table->string('recipient_name');
            $table->string('recipient_number');
            $table->string('recipient_type');
            $table->text('message');
            $table->enum('status', [
                'sent',
                'failed',
                'pending'
            ])->default('pending');
            $table->string('sms_provider')->default('semaphore');
            $table->timestamp('sent_at')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sms_logs');
    }
};

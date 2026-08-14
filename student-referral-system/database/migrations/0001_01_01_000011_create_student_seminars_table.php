<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('student_seminars', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('seminar_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->enum('status', [
                'enrolled',
                'attended',
                'missed',
                'excused'
            ])->default('enrolled');
            $table->timestamp('attended_at')->nullable();
            $table->string('remarks')->nullable();
            $table->timestamps();

            // One record per student per seminar
            $table->unique(['student_id', 'seminar_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('student_seminars');
    }
};

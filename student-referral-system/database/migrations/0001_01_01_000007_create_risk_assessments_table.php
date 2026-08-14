<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('student_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->decimal('avg_grade', 5, 2)->default(0);
            $table->integer('total_absences')->default(0);
            $table->integer('behavioral_reports_count')->default(0);
            $table->integer('failed_subjects')->default(0);
            $table->decimal('risk_score', 5, 2)->default(0);
            $table->enum('risk_level', [
                'low',
                'moderate',
                'high'
            ])->default('low');
            $table->json('risk_factors')->nullable();
            $table->timestamp('assessed_at')->useCurrent();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};

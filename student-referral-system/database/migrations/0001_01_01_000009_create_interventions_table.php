<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interventions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('referral_id')
                  ->constrained()
                  ->onDelete('cascade');
            $table->foreignId('counselor_id')
                  ->constrained('users')
                  ->onDelete('cascade');
            $table->string('intervention_type');
            $table->text('description');
            $table->date('intervention_date');
            $table->enum('outcome', [
                'improving',
                'no_change',
                'worsening',
                'resolved'
            ])->nullable();
            $table->text('follow_up_notes')->nullable();
            $table->date('follow_up_date')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interventions');
    }
};

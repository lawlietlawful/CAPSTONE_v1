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
        // 1. Drop the attendance table entirely
        Schema::dropIfExists('attendance');

        // 2. Add concern_type to referrals (if not already added)
        if (!Schema::hasColumn('referrals', 'concern_type')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->enum('concern_type', [
                    'academic',
                    'behavioral',
                    'emotional',
                    'family',
                    'peer_conflict',
                    'attendance',
                    'other'
                ])->default('other')->after('referral_type');
            });
        }

        // 3. Update risk_assessments: remove old columns if they exist
        Schema::table('risk_assessments', function (Blueprint $table) {
            $columns = Schema::getColumnListing('risk_assessments');
            $toDrop = [];
            foreach (['tardiness', 'misconduct', 'total_absences', 'failed_subjects'] as $col) {
                if (in_array($col, $columns)) {
                    $toDrop[] = $col;
                }
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        // 4. Add new columns if they don't exist
        Schema::table('risk_assessments', function (Blueprint $table) {
            $columns = Schema::getColumnListing('risk_assessments');
            if (!in_array('previous_referrals_count', $columns)) {
                $table->integer('previous_referrals_count')->default(0)->after('student_id');
            }
            // behavioral_reports_count already exists from original migration
            if (!in_array('concern_type_encoded', $columns)) {
                $table->string('concern_type_encoded')->nullable()->after('behavioral_reports_count');
            }
            if (!in_array('days_since_last_referral', $columns)) {
                $table->integer('days_since_last_referral')->default(999)->after('concern_type_encoded');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('risk_assessments', function (Blueprint $table) {
            $columns = Schema::getColumnListing('risk_assessments');
            $toDrop = [];
            foreach (['previous_referrals_count', 'concern_type_encoded', 'days_since_last_referral'] as $col) {
                if (in_array($col, $columns)) {
                    $toDrop[] = $col;
                }
            }
            if (!empty($toDrop)) {
                $table->dropColumn($toDrop);
            }
        });

        Schema::table('risk_assessments', function (Blueprint $table) {
            $columns = Schema::getColumnListing('risk_assessments');
            if (!in_array('tardiness', $columns)) {
                $table->integer('tardiness')->default(0)->after('student_id');
            }
            if (!in_array('misconduct', $columns)) {
                $table->integer('misconduct')->default(0)->after('tardiness');
            }
            if (!in_array('total_absences', $columns)) {
                $table->integer('total_absences')->default(0)->after('misconduct');
            }
            if (!in_array('failed_subjects', $columns)) {
                $table->integer('failed_subjects')->default(0)->after('behavioral_reports_count');
            }
        });

        if (Schema::hasColumn('referrals', 'concern_type')) {
            Schema::table('referrals', function (Blueprint $table) {
                $table->dropColumn('concern_type');
            });
        }

        // Recreate attendance table
        if (!Schema::hasTable('attendance')) {
            Schema::create('attendance', function (Blueprint $table) {
                $table->id();
                $table->foreignId('student_id')->constrained()->onDelete('cascade');
                $table->foreignId('teacher_id')->constrained('users')->onDelete('cascade');
                $table->date('date');
                $table->enum('status', ['present', 'absent', 'late', 'excused'])->default('present');
                $table->string('absence_type')->nullable();
                $table->text('remarks')->nullable();
                $table->timestamps();
            });
        }
    }
};

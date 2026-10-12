<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes for the filters every page and poll runs: the bell's unread
 * count, open-referral lookups, report/student status, follow-up dates.
 * Small tables don't notice; at hundreds of thousands of rows these turn
 * full-table scans into index lookups.
 */
return new class extends Migration
{
    private array $indexes = [
        'notifications'      => [['user_id', 'is_read']],
        'referrals'          => [['status'], ['counselor_id', 'status'], ['student_id', 'status']],
        'behavioral_reports' => [['status']],
        'students'           => [['status']],
        'interventions'      => [['follow_up_date']],
    ];

    public function up(): void
    {
        foreach ($this->indexes as $table => $sets) {
            Schema::table($table, function (Blueprint $t) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $t->index($columns, $this->name($table, $columns));
                }
            });
        }
    }

    public function down(): void
    {
        foreach ($this->indexes as $table => $sets) {
            Schema::table($table, function (Blueprint $t) use ($table, $sets) {
                foreach ($sets as $columns) {
                    $t->dropIndex($this->name($table, $columns));
                }
            });
        }
    }

    private function name(string $table, array $columns): string
    {
        return $table . '_' . implode('_', $columns) . '_scale_index';
    }
};

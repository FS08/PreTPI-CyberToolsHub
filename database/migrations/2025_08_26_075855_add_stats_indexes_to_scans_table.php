<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            // Index for stats queries (user_id + created_at)
            $table->index(['user_id', 'created_at'], 'scans_user_created_idx');

            // Index for heuristics_score (if column exists in your DB)
            if (Schema::hasColumn('scans', 'heuristics_score')) {
                $table->index(['user_id', 'heuristics_score'], 'scans_user_score_idx');
            }
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropIndex('scans_user_created_idx');
            if (Schema::hasColumn('scans', 'heuristics_score')) {
                $table->dropIndex('scans_user_score_idx');
            }
        });
    }
};

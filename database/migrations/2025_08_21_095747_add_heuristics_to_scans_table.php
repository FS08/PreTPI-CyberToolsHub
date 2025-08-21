<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->json('heuristics_json')->nullable()->after('spf_json');
            $table->unsignedTinyInteger('risk_score')->default(0)->after('heuristics_json');
        });
    }

    public function down(): void
    {
        Schema::table('scans', function (Blueprint $table) {
            $table->dropColumn(['heuristics_json','risk_score']);
        });
    }
};
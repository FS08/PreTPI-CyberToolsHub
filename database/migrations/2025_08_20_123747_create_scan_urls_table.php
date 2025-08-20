<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('scan_urls', function (Blueprint $table) {
            $table->id();
            $table->foreignId('scan_id')->constrained()->cascadeOnDelete();

            $table->string('url', 2048);
            $table->string('host')->nullable();

            // urlscan submission control/result
            $table->string('visibility', 16)->default('unlisted'); // public|unlisted
            $table->string('status', 20)->default('submitted');    // submitted|blocked|rate_limited|error
            $table->string('urlscan_uuid', 64)->nullable();
            $table->string('result_url')->nullable();

            // errors
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_urls');
    }
};
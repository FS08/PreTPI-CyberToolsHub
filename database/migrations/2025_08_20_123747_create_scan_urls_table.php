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
            $table->string('visibility', 16)->default('unlisted'); 
            $table->string('status', 20)->default('queued'); // queued|submitted|finished|blocked|rate_limited|error
            $table->string('result_uuid', 64)->nullable();   // renamed from urlscan_uuid
            $table->string('result_url')->nullable();

            // errors
            $table->string('error_code')->nullable();
            $table->text('error_message')->nullable();

            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('finished_at')->nullable(); // new: when marked finished
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_urls');
    }
};
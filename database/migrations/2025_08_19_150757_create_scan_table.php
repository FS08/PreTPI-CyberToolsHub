<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('scans', function (Blueprint $table) {
            $table->id();

            // Link scan to the user who uploaded the .eml
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Basic headers (store minimal PII only)
            $table->string('from')->nullable();
            $table->string('from_domain')->nullable();
            $table->string('to')->nullable();
            $table->string('subject')->nullable();

            // Dates
            $table->string('date_raw')->nullable();      // as-is from the email header
            $table->timestamp('date_iso')->nullable();   // normalized (Carbon) if parseable

            // Bodies (lengths only; no content persisted)
            $table->unsignedInteger('text_length')->default(0);
            $table->unsignedInteger('html_length')->default(0);
            $table->unsignedInteger('raw_size')->default(0); // bytes of the raw .eml

            // Attachments & URLs
            $table->unsignedInteger('attachments_count')->default(0);
            $table->unsignedInteger('urls_count')->default(0);

            // Optional: store the extracted URLs as JSON for convenience (no body content)
            $table->json('urls_json')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scans');
    }
};
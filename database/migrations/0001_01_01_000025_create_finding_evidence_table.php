<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A dedicated table rather than reusing `evidence` (docs/05-DATABASE-SCHEMA.md
        // §26): PIC work evidence is owned by a Submission with a whole
        // Work-Item lock lifecycle attached; a Manager's ISO-audit response
        // file has neither, so sharing the table would force an unused
        // submission_id and mix two unrelated lifecycles.
        Schema::create('finding_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('finding_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key');
            $table->json('metadata')->nullable();
            $table->dateTime('uploaded_at');
            $table->timestamps();

            $table->index('finding_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finding_evidence');
    }
};

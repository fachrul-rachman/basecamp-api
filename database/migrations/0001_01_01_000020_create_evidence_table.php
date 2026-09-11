<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('submission_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key');
            $table->string('source_type'); // upload | camera
            $table->dateTime('captured_at')->nullable();
            $table->dateTime('uploaded_at');
            $table->json('metadata')->nullable();
            $table->string('ai_status')->nullable();
            $table->decimal('ai_score', 5, 2)->nullable();
            $table->string('human_review_status')->nullable();
            $table->timestamps();

            $table->index('submission_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence');
    }
};

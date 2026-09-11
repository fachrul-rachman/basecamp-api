<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('template_reference_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_template_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('template_reference_evidence');
    }
};

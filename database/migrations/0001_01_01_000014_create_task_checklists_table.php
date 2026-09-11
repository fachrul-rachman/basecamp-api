<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('task_checklists', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('source_template_checklist_id')->nullable()
                ->constrained('task_template_checklists')->nullOnDelete();
            $table->string('title');
            $table->text('instructions')->nullable();
            // Defaults to the task's owner department at creation time when
            // omitted (see docs/05-DATABASE-SCHEMA.md §13); always resolved
            // before insert, so this column itself is required.
            $table->foreignUuid('target_department_id')->constrained('departments')->restrictOnDelete();
            $table->string('schedule_type');
            $table->json('schedule_config');
            $table->unsignedInteger('evidence_min_count')->default(0);
            $table->boolean('allow_upload')->default(true);
            $table->boolean('allow_camera')->default(true);
            $table->boolean('works_on_holidays')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['task_id', 'is_active']);
        });

        Schema::create('checklist_reference_evidence', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('task_checklist_id')->constrained()->cascadeOnDelete();
            $table->string('storage_key');
            $table->json('metadata')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('checklist_reference_evidence');
        Schema::dropIfExists('task_checklists');
    }
};
